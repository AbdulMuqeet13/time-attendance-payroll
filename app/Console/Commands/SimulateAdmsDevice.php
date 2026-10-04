<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Pretends to be a ZKTeco device talking ADMS to this app, for testing without hardware.
 */
#[Signature('adms:simulate
    {serial=SIM0000001 : Device serial number}
    {--url= : Server base URL (defaults to APP_URL)}
    {--scan=* : Scans to push as PIN@Y-m-d H:i:s, e.g. --scan="7@2026-10-05 09:01:00"}
    {--polls=1 : How many times to poll for commands (results are acknowledged as successful)}')]
#[Description('Simulate a ZKTeco ADMS device: handshake, push scans and answer queued commands')]
class SimulateAdmsDevice extends Command
{
    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('The device simulator is disabled in production.');

            return self::FAILURE;
        }

        $base = rtrim($this->option('url') ?: config('app.url'), '/').'/iclock';
        $serial = $this->argument('serial');

        $handshake = Http::get("{$base}/cdata", ['SN' => $serial, 'options' => 'all', 'pushver' => '2.4.1']);
        $this->line('<info>Handshake</info>');
        $this->line(trim($handshake->body()));

        $scans = collect($this->option('scan'))
            ->map(fn (string $scan) => explode('@', $scan, 2))
            ->filter(fn (array $parts) => count($parts) === 2)
            ->map(fn (array $parts) => "{$parts[0]}\t{$parts[1]}\t0\t1\t0\t0")
            ->implode("\n");

        if ($scans !== '') {
            $push = Http::withBody($scans, 'text/plain')->post("{$base}/cdata?SN={$serial}&table=ATTLOG&Stamp=".time());
            $this->line('<info>Pushed scans:</info> '.trim($push->body()));
        }

        for ($poll = 1; $poll <= (int) $this->option('polls'); $poll++) {
            $response = trim(Http::get("{$base}/getrequest", ['SN' => $serial, 'INFO' => 'Ver 8.0.4-SIM,3,6,120,127.0.0.1,10,7,12,0'])->body());
            $this->line("<info>Poll {$poll}:</info> ".str_replace("\t", '→', $response));

            preg_match_all('/^C:(\d+):/m', $response, $matches);

            if ($matches[1] !== []) {
                $results = collect($matches[1])->map(fn (string $id) => "ID={$id}&Return=0&CMD=DATA")->implode("\n");
                Http::withBody($results, 'text/plain')->post("{$base}/devicecmd?SN={$serial}");
                $this->line('  acknowledged commands '.implode(', ', $matches[1]));
            }
        }

        return self::SUCCESS;
    }
}
