import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

/**
 * How to point a ZKTeco device at this server (from the F22 setup notes).
 */
export function DeviceSetupCard({ serverUrl }: { serverUrl: string }) {
    const url = new URL(serverUrl);
    const port = url.port || (url.protocol === 'https:' ? '443' : '80');

    return (
        <Card>
            <CardHeader>
                <CardTitle>Connect a device</CardTitle>
            </CardHeader>
            <CardContent>
                <ol className="list-decimal space-y-1 pl-5 text-sm text-muted-foreground">
                    <li>
                        Network → Ethernet: turn <strong>DHCP on</strong> (or
                        set a fixed IP).
                    </li>
                    <li>
                        System → Device Type: choose{' '}
                        <strong>T&amp;A Push</strong>.
                    </li>
                    <li>
                        Cloud Server Settings: server{' '}
                        <code className="rounded bg-muted px-1 font-mono">
                            {url.hostname}
                        </code>
                        , port{' '}
                        <code className="rounded bg-muted px-1 font-mono">
                            {port}
                        </code>
                        {url.protocol === 'https:' ? ', HTTPS on' : ''}. If the
                        device has a path field, use{' '}
                        <code className="rounded bg-muted px-1 font-mono">
                            /iclock
                        </code>
                        .
                    </li>
                    <li>Save and reboot the device.</li>
                    <li>
                        It appears here as <strong>Unclaimed</strong> within a
                        minute. Claim it for a branch to start recording scans.
                    </li>
                </ol>
            </CardContent>
        </Card>
    );
}
