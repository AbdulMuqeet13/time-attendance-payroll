<?php

namespace App\Http\Controllers\Adms;

use App\Events\DeviceDataReceived;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Adms\AdmsParser;
use App\Services\Adms\DeviceCommandQueue;
use App\Services\Adms\DeviceDataHandler;
use App\Services\Adms\DeviceRegistry;
use App\Services\Adms\PunchRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * ZKTeco ADMS (PUSH SDK) endpoints. Devices are configured with this server's address and path /iclock.
 *
 *  1. GET  /iclock/cdata?SN=…&options=all    handshake; we reply with options and our last stamps
 *  2. POST /iclock/cdata?SN=…&table=ATTLOG   attendance scans (also OPERLOG, USERINFO, FINGERTMP, BIODATA)
 *  3. GET  /iclock/getrequest?SN=…&INFO=…    device polls for queued commands ("C:{id}:{command}" or "OK")
 *  4. POST /iclock/devicecmd?SN=…            command results ("ID=…&Return=…&CMD=…")
 *  5. POST /iclock/querydata?SN=…            PUSH 3.x answers to table queries
 *
 * Data from devices that no admin has claimed to a branch is acknowledged but ignored.
 */
class IclockController extends Controller
{
    public function __construct(
        private DeviceRegistry $registry,
        private DeviceCommandQueue $queue,
        private AdmsParser $parser,
    ) {}

    public function handshake(Request $request): Response
    {
        $serialNumber = $this->serialNumber($request);

        if ($serialNumber === null) {
            return $this->text('Error: missing SN', 400);
        }

        $device = $this->registry->findOrRegister($serialNumber, $request->ip());
        $this->registry->touch($device, $request->ip(), null, $request->query('pushver'));

        return $this->text(implode("\n", [
            "GET OPTION FROM: {$serialNumber}",
            'ATTLOGStamp='.($device->last_attlog_stamp ?? '0'),
            'OPERLOGStamp='.($device->last_operlog_stamp ?? '0'),
            'ATTPHOTOStamp=None',
            'ErrorDelay=30',
            'Delay=5',
            'TransTimes=00:00;14:05',
            'TransInterval=1',
            'TransFlag=TransData AttLog OpLog EnrollUser ChgUser EnrollFP ChgFP FACE UserPic',
            'TimeZone='.now()->offsetHours,
            'Realtime=1',
            'Encrypt=None',
            'ServerVer=2.4.1',
            'PushProtVer=2.4.1',
        ])."\n");
    }

    public function registry(Request $request): Response
    {
        $serialNumber = $this->serialNumber($request);

        if ($serialNumber !== null) {
            $device = $this->registry->findOrRegister($serialNumber, $request->ip());
            $this->registry->touch($device, $request->ip(), null, $request->query('pushver'));
        }

        return $this->text("RegistryCode=200\n");
    }

    public function upload(Request $request, PunchRecorder $recorder, DeviceDataHandler $dataHandler): Response
    {
        $device = $this->registry->find($this->serialNumber($request));
        $table = strtoupper((string) $request->query('table'));

        if (! $device) {
            return $this->text('OK');
        }

        $this->registry->touch($device, $request->ip());

        if (! $device->acceptsData()) {
            Log::notice('ADMS: ignored data from unclaimed or disabled device', ['sn' => $device->serial_number, 'table' => $table]);

            return $this->text('OK');
        }

        $body = $request->getContent();

        if ($table === 'ATTLOG') {
            $logs = $this->parser->attendanceLogs($body);
            $recorded = $recorder->recordDeviceLogs($device, $logs);
            DeviceDataReceived::dispatch($device, 'attlog', $logs);
            $this->advanceStamp($device, 'last_attlog_stamp', $request->query('Stamp'));

            return $this->text("OK: {$recorded}");
        }

        if (in_array($table, ['OPERLOG', 'USERINFO', 'FINGERTMP', 'BIODATA', 'FACE', 'USERPIC'], true)) {
            $handled = $dataHandler->handle($device, $table, $body);

            if ($table === 'OPERLOG') {
                $this->advanceStamp($device, 'last_operlog_stamp', $request->query('OpStamp') ?? $request->query('Stamp'));
            }

            return $this->text("OK: {$handled}");
        }

        return $this->text('OK');
    }

    /**
     * PUSH 3.x answers to "DATA QUERY tablename=…" commands.
     */
    public function queryData(Request $request, PunchRecorder $recorder, DeviceDataHandler $dataHandler): Response
    {
        $device = $this->registry->find($this->serialNumber($request));
        $table = strtolower((string) $request->query('tablename'));

        if (! $device || ! $device->acceptsData()) {
            return $this->text('OK');
        }

        $this->registry->touch($device, $request->ip());
        $body = $request->getContent();

        if ($table === 'transaction') {
            $logs = $this->parser->attendanceLogs($body);
            $count = $recorder->recordDeviceLogs($device, $logs);
            DeviceDataReceived::dispatch($device, 'attlog', $logs);
        } else {
            $count = $dataHandler->handle($device, $table, $body);
        }

        return $this->text("{$table}={$count}");
    }

    public function poll(Request $request): Response
    {
        $device = $this->registry->find($this->serialNumber($request));

        if (! $device) {
            return $this->text('OK');
        }

        $this->registry->touch($device, $request->ip(), $request->query('INFO'));

        if (! $device->acceptsData()) {
            return $this->text('OK');
        }

        $commands = $this->queue->deliver($device);

        if ($commands->isEmpty()) {
            return $this->text('OK');
        }

        return $this->text($commands->map(fn ($command) => "C:{$command->sequence}:{$command->command}")->implode("\n")."\n");
    }

    public function commandResult(Request $request): Response
    {
        $device = $this->registry->find($this->serialNumber($request));

        if ($device) {
            $this->registry->touch($device, $request->ip());
            $body = $request->getContent();
            $this->queue->recordResults($device, $this->parser->commandResults($body), $body);
        }

        return $this->text('OK');
    }

    /**
     * Some models call undocumented paths; answering OK keeps them working.
     */
    public function fallback(Request $request): Response
    {
        Log::debug('ADMS: unknown path', ['path' => $request->path(), 'method' => $request->method(), 'query' => $request->query()]);

        return $this->text('OK');
    }

    private function serialNumber(Request $request): ?string
    {
        $serialNumber = trim((string) $request->query('SN', $request->query('sn', '')));

        return $serialNumber !== '' ? mb_substr($serialNumber, 0, 64) : null;
    }

    /**
     * Remember the newest stamp the device sent, so the next handshake tells it to skip what we already have.
     */
    private function advanceStamp(Device $device, string $column, mixed $stamp): void
    {
        if (! is_numeric($stamp)) {
            return;
        }

        if ((int) $stamp > (int) ($device->{$column} ?? 0)) {
            $device->update([$column => (string) $stamp]);
        }
    }

    private function text(string $body, int $status = 200): Response
    {
        return response($body, $status, ['Content-Type' => 'text/plain']);
    }
}
