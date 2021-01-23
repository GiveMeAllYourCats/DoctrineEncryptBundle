<?php

namespace Ambta\DoctrineEncryptBundle\Tests\Unit\Encryptors;

use Ambta\DoctrineEncryptBundle\Encryptors\DefuseEncryptor;
use PHPUnit\Framework\TestCase;

class DefuseEncryptorTest extends TestCase
{
    private const DATA = 'foobar';

    public function testEncrypt(): void
    {
        $keyfile = __DIR__.'/fixtures/defuse.key';
        $key = file_get_contents($keyfile);
        $defuse = new DefuseEncryptor($keyfile);

        $encrypted = $defuse->encrypt(self::DATA);
        $this->assertNotSame(self::DATA, $encrypted);
        $decrypted = $defuse->decrypt($encrypted);

        $this->assertSame(self::DATA, $decrypted);
        $newkey = file_get_contents($keyfile);
        $this->assertSame($key, $newkey, 'The key must not be modified');
    }

    public function testGenerateKey(): void
    {
        $keyfile = sys_get_temp_dir().'/defuse-'.md5(time());
        if (file_exists($keyfile)) {
            unlink($keyfile);
        }
        $defuse = new DefuseEncryptor($keyfile);
        $defuse->encrypt(self::DATA);

        $this->assertFileExists($keyfile);
        $this->assertNotEmpty(file_get_contents($keyfile), 'A key should have been created and saved to the file');

        unlink($keyfile);
    }
}
