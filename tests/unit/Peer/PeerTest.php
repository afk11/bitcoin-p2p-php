<?php

declare(strict_types=1);

namespace BitWasp\Bitcoin\Tests\Networking\Peer;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Networking\Ip\Ipv4;
use BitWasp\Bitcoin\Networking\Messages\Version;
use BitWasp\Bitcoin\Networking\Peer\ConnectionParams;
use BitWasp\Bitcoin\Networking\Peer\Peer;
use BitWasp\Bitcoin\Networking\Structure\NetworkAddressInterface;
use BitWasp\Bitcoin\Tests\Networking\TestCase;
use BitWasp\Buffertools\Buffer;
use React\EventLoop\StreamSelectLoop;

class PeerTest extends TestCase
{
    protected function expectCallable($type)
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($type)
            ->method('__invoke');
        return $mock;
    }

    protected function createCallableMock()
    {
        return $this->getMock(CallableStub::class);
    }

    public function services($int)
    {
        $math = Bitcoin::getMath();
        $hex = $math->decHex($int);
        $buffer = Buffer::hex($hex, 8);
        return $buffer;
    }

    public function testPeer()
    {
        $localhost = new Ipv4('9.9.9.9');
        $remotehost = new Ipv4('127.0.0.1');
        $remoteport = 9999;

        $loop = new StreamSelectLoop();

        $network = Bitcoin::getDefaultNetwork();
        $factory = new \BitWasp\Bitcoin\Networking\Factory($loop, $network);

        $server = $factory->getAddress($remotehost, $remoteport);

        $params = new ConnectionParams();
        $params->setLocalIp($localhost);
        $params->setBestBlockHeight(100);

        /** @var Version $serverReceivedVersion */
        $serverReceivedVersion = null;
        /** @var NetworkAddressInterface $serverInboundAddr */
        $serverInboundAddr = null;
        $serverReceivedConnection = false;
        $serverListener = $factory->getListener($params, $server);
        $serverListener->on('connection', function (Peer $peer) use (&$serverReceivedConnection, &$serverReceivedVersion, &$serverInboundAddr) {
            $peer->close();
            $serverReceivedConnection = true;
            $serverInboundAddr = $peer->getRemoteAddress();
            $serverReceivedVersion = $peer->getRemoteVersion();
        });

        $connector = $factory->getConnector($params);

        $localVersion = null;
        $localParams = null;

        $connector->connect($server)->then(
            function (Peer $peer) use ($serverListener, &$loop, &$localVersion, &$localParams) {
                $peer->close();
                $serverListener->close();
                $localVersion = $peer->getLocalVersion();
                $localParams = $peer->getConnectionParams();
            }
        );

        $loop->run();

        $this->assertTrue($serverReceivedConnection);
        $this->assertEquals($localhost->getHost(), $serverReceivedVersion->getSenderAddress()->getIp()->getHost());
        $this->assertEquals(100, $serverReceivedVersion->getStartHeight());
        $this->assertEquals('bitcoin-php', $serverReceivedVersion->getUserAgent()->getBinary());
        
        $this->assertSame($params, $localParams);
    }
}
