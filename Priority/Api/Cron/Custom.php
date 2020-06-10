<?php
namespace Priority\Api\Cron;
use \Psr\Log\LoggerInterface;

class Custom {
	protected $logger;
    public function __construct(LoggerInterface $logger) {
		$this->logger = $logger;
	}
	public function execute() {	
		chmod("/var/www/html/var/cache", 0777);
		chmod("/var/www/html/pub/static", 0777);
		chmod("/var/www/html/generated", 0777);
		$this->logger->info('Cron Works');
	}
}