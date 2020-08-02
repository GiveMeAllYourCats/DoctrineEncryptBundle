<?php


namespace Ambta\DoctrineEncryptBundle\Tests\Functional\fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity */
class ClassTableInheritanceChild extends ClassTableInheritanceBase
{

    /**
     * @Ambta\DoctrineEncryptBundle\Configuration\Encrypted()
     * @ORM\Column(type="string", nullable=true)
     */
    private $secretChild;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $notSecretChild;

    public function getSecretChild()
    {
        return $this->secretChild;
    }

    public function setSecretChild($secretChild)
    {
        $this->secretChild = $secretChild;
    }

    /**
     * @return mixed
     */
    public function getNotSecretChild()
    {
        return $this->notSecretChild;
    }

    /**
     * @param mixed $notSecretChild
     */
    public function setNotSecretChild($notSecretChild)
    {
        $this->notSecretChild = $notSecretChild;
    }

}