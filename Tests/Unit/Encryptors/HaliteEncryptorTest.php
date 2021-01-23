<?php

namespace Ambta\DoctrineEncryptBundle\Tests\Unit\Encryptors;

use Ambta\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;
use PHPUnit\Framework\TestCase;

class HaliteEncryptorTest extends TestCase
{
    private const DATA = 'foobar';

    public function testEncryptExtension(): void
    {
        if (! extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyfile = __DIR__.'/fixtures/halite.key';
        $key = file_get_contents($keyfile);
        $halite = new HaliteEncryptor($keyfile);

        $encrypted = $halite->encrypt(self::DATA);
        $this->assertNotSame(self::DATA, $encrypted);
        $decrypted = $halite->decrypt($encrypted);

        $this->assertSame(self::DATA, $decrypted);
        $newkey = file_get_contents($keyfile);
        $this->assertSame($key, $newkey, 'The key must not be modified');
    }

    public function testGenerateKey(): void
    {
        if (! extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyfile = sys_get_temp_dir().'/halite-'.md5(time());
        if (file_exists($keyfile)) {
            unlink($keyfile);
        }
        $halite = new HaliteEncryptor($keyfile);
        $halite->encrypt(self::DATA);

        $this->assertFileExists($keyfile);
        $this->assertNotEmpty(file_get_contents($keyfile), 'A key should have been created and saved to the file');

        unlink($keyfile);
    }


    public function testEncryptWithoutExtensionThrowsException(): void
    {
        if (extension_loaded('sodium')) {
            $this->markTestSkipped('This only runs when the sodium extension is disabled.');
        }
        $keyfile = __DIR__.'/fixtures/halite.key';
        $halite = new HaliteEncryptor($keyfile);

        $this->expectException(\SodiumException::class);
        $halite->encrypt(self::DATA);
    }

}
