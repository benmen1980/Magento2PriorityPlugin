<?php
namespace Priority\Api\Cron;
use \Psr\Log\LoggerInterface;

class Custom {
	protected $logger;
    public function __construct(LoggerInterface $logger) {
		$this->logger = $logger;
	}
	public function execute() {	
		chmod("/var/www/paneco/releases/20200608072535/src/var/cache", 0777);
		chmod("/var/www/paneco/releases/20200608072535/src/pub/static", 0777);
		chmod("/var/www/paneco/releases/20200608072535/src/generated", 0777);
		$this->logger->info('Cron Works');
	}
}