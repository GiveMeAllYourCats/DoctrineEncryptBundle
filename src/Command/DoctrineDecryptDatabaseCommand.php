<?php

namespace Ambta\DoctrineEncryptBundle\Command;

use Ambta\DoctrineEncryptBundle\DependencyInjection\DoctrineEncryptExtension;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Decrypt whole database on tables which are encrypted
 *
 * @author Marcel van Nuil <marcel@ambta.com>
 * @author Michael Feinbier <michael@feinbier.net>
 */
class DoctrineDecryptDatabaseCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('doctrine:decrypt:database')
            ->setDescription('Decrypt whole database on tables which are encrypted')
            ->addArgument('encryptor', InputArgument::OPTIONAL, 'The encryptor you want to decrypt the database with')
            ->addArgument('batchSize', InputArgument::OPTIONAL, 'The update/flush batch size', 20);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get entity manager, question helper, subscriber service and annotation reader
        $question = $this->getHelper('question');

        // Get list of supported encryptors
        $supportedExtensions = DoctrineEncryptExtension::SupportedEncryptorClasses;
        $batchSize = $input->getArgument('batchSize');

        // If encryptor has been set use that encryptor else use default
        if ($input->getArgument('encryptor')) {
            if (isset($supportedExtensions[$input->getArgument('encryptor')])) {
                $reflection = new \ReflectionClass($supportedExtensions[$input->getArgument('encryptor')]);
                $encryptor = $reflection->newInstance();
                $this->subscriber->setEncryptor($encryptor);
            } else {
                if (class_exists($input->getArgument('encryptor'))) {
                    $this->subscriber->setEncryptor($input->getArgument('encryptor'));
                } else {
                    $output->writeln('Given encryptor does not exists');

                    return $output->writeln('Supported encryptors: ' . implode(', ', array_keys($supportedExtensions)));
                }
            }
        }

        // Get entity manager metadata
        $metaDataArray = $this->entityManager->getMetadataFactory()->getAllMetadata();

        // Set counter and loop through entity manager meta data
        $propertyCount = 0;
        foreach ($metaDataArray as $metaData) {
            if ($metaData instanceof ClassMetadataInfo and $metaData->isMappedSuperclass) {
                continue;
            }

            $countProperties = count($this->getEncryptionableProperties($metaData));
            $propertyCount += $countProperties;
        }

        $confirmationQuestion = new ConfirmationQuestion(
            '<question>' . count($metaDataArray) . ' entities found which are containing ' . $propertyCount . ' properties with the encryption tag. ' . PHP_EOL . '' .
            'Which are going to be decrypted with [' . get_class($this->subscriber->getEncryptor()) . ']. ' . PHP_EOL . '' .
            'Wrong settings can mess up your data and it will be unrecoverable. ' . PHP_EOL . '' .
            'I advise you to make <bg=yellow;options=bold>a backup</bg=yellow;options=bold>. ' . PHP_EOL . '' .
            'Continue with this action? (y/yes)</question>', false
        );

        if (!$question->ask($input, $output, $confirmationQuestion)) {
            return 1;
        }

        // Start decrypting database
        $output->writeln('' . PHP_EOL . 'Decrypting all fields. This can take up to several minutes depending on the database size.');

        $valueCounter = 0;

        // Loop through entity manager meta data
        foreach ($this->getEncryptionableEntityMetaData() as $metaData) {
            $i = 0;
            $iterator = $this->getEntityIterator($metaData->name);
            $totalCount = $this->getTableCount($metaData->name);

            $output->writeln(sprintf('Processing <comment>%s</comment>', $metaData->name));
            $progressBar = new ProgressBar($output, $totalCount);
            foreach ($iterator as $row) {
                $entity = $row[0];

                // Create reflectionClass for each entity
                $entityReflectionClass = new \ReflectionClass($entity);

                //Get the current encryptor used
                $encryptorUsed = $this->subscriber->getEncryptor();

                //Loop through the property's in the entity
                foreach ($this->getEncryptionableProperties($metaData) as $property) {
                    $methodeName = ucfirst($property->getName());

                    $getter = 'get' . $methodeName;
                    $setter = 'set' . $methodeName;

                    //Check if getter and setter are set
                    if ($entityReflectionClass->hasMethod($getter) && $entityReflectionClass->hasMethod($setter)) {
                        $unencrypted = $entity->$getter();
                        $entity->$setter($unencrypted);
                        $valueCounter++;
                    }
                }

                $this->subscriber->setEncryptor(null);
                $this->entityManager->persist($entity);

                if (($i % $batchSize) === 0) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }
                $progressBar->advance(1);
                $i++;

                $this->subscriber->setEncryptor($encryptorUsed);
            }


            $progressBar->finish();
            $output->writeln('');
            $encryptorUsed = $this->subscriber->getEncryptor();
            $this->subscriber->setEncryptor(null);
            $this->entityManager->flush();
            $this->entityManager->clear();
            $this->subscriber->setEncryptor($encryptorUsed);
        }

        $output->writeln('' . PHP_EOL . 'Decryption finished values found: <info>' . $valueCounter . '</info>, decrypted: <info>' . $this->subscriber->decryptCounter . '</info>.' . PHP_EOL . 'All values are now decrypted.');
        
        return 0;
    }
}
