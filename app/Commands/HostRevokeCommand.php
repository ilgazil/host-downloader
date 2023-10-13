<?php

namespace App\Commands;

use App\Exceptions\DriverExceptions\NoMatchingDriverException;
use Ilgazil\LibDownload\Driver\DriverService;

class HostRevokeCommand extends AbstractCommand
{
    protected $signature = 'host:revoke {driver : driver name}';

    protected $description = 'Unauthenticate on a host, and remove saved credentials';

    protected DriverService $driverService;

    public function __construct(DriverService $driverService)
    {
        parent::__construct();

        $this->driverService = $driverService;
    }

    /**
     * @throws NoMatchingDriverException
     */
    protected function _handle()
    {
        $driver = $this->driverService->findByName($this->argument('driver'));
        $driver->unauthenticate();

        $this->line('Disconnected of ' . $driver->getName());
    }
}
