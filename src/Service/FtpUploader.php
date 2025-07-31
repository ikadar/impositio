<?php

namespace App\Service;

use App\Domain\Equipment\Interfaces\EquipmentFactoryInterface;
use App\Service\Interfaces\FtpUploaderInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class FtpUploader implements FtpUploaderInterface
{

    public function __construct(
        protected KernelInterface $kernel,
        protected SluggerInterface $slugger,
        protected EquipmentFactoryInterface $equipmentFactory,

    )
    {
    }

    public function uploadFtpFiles($job)
    {

        $localFileSystem = new Filesystem(new LocalFilesystemAdapter(
            sprintf("%s/resources/", $this->kernel->getProjectDir())
        ));

        $filesystem = new Filesystem(new SftpAdapter(
            new SftpConnectionProvider(
                $_ENV["ORDO_FTP_HOST"], // host (required)
                $_ENV["ORDO_FTP_USER"], // username (required)
                $_ENV["ORDO_FTP_PASSWORD"], // password (optional, default: null) set to null if privateKey is used
                null, // private key (optional, default: null) can be used instead of password, set to null if password is set
                null, // passphrase (optional, default: null), set to null if privateKey is not used or has no passphrase
                2222, // port (optional, default: 22)
                false, // use agent (optional, default: false)
                30, // timeout (optional, default: 10)
                10, // max tries (optional, default: 4)
                null, // host fingerprint (optional, default: null),
                null, // connectivity checker (must be an implementation of 'League\Flysystem\PhpseclibV2\ConnectivityChecker' to check if a connection can be established (optional, omit if you don't need some special handling for setting reliable connections)
            ),
            '/in', // root path (required)
            PortableVisibilityConverter::fromArray([
                'file' => [
                    'public' => 0640,
                    'private' => 0604,
                ],
                'dir' => [
                    'public' => 0740,
                    'private' => 7604,
                ],
            ])
        ));


        $this->writeMachines($job, $filesystem);
        $this->writeHR($localFileSystem, $filesystem);

        $filesystem->write(sprintf('jobs/%s.json', $job["id"]), json_encode($job, JSON_PRETTY_PRINT));


    }

    protected function writeHR($localFileSystem, $ftpFileSystem)
    {
        $hr = $localFileSystem->listContents("hr");
        /**
         * @var $file \League\Flysystem\FileAttributes
         */
        foreach ($hr as $file) {
            $ftpFileSystem->write(
                sprintf("rh/%s", pathinfo($file->path())["basename"]),
                $localFileSystem->read($file->path())
            );
        }

    }

    protected function writeMachines($job, $ftpFileSystem)
    {
        $machineIds = [];
        foreach ($job["parts"] as $part) {
            foreach ($part["actions"] as $action) {
                $machineIds[] = $action["machine"];
            }
        }
        $machineIds = array_unique($machineIds);

        $machines = [];
        foreach ($machineIds as $machineId) {
            $machines[] = $this->equipmentFactory->fromId($machineId);
        }

        foreach ($machines as $machine) {
            $machineConfigurationFilePath = sprintf("machines/%s.json", $this->slugger->slug($machine->getId()));
            $ftpFileSystem->write($machineConfigurationFilePath, json_encode($machine->getOrdoData(), JSON_PRETTY_PRINT));
        }

        $dryingMachine = <<<DRY
{
  "id": "sechage",
  "designation": "séchage",
  "designation_technique": "séchage",
  "type": "temps incompressible",
  "capacite": 9999,
  "peremption_calage": 999999,
  "regime_nominal": {
    "attention_requise": 0,
    "productivite": 1
  }
}
DRY;

        $ftpFileSystem->write('machines/sechage.json', $dryingMachine);

    }
}