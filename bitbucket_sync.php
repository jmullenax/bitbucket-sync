<?php

	class bitbucketSync {

		public $externalOptionsFile = 'config.json';
		public $config = array();

		public $apiUrl = 'https://bitbucket.org/api/2.0/';
		public $hgUrl = 'ssh://hg@bitbucket.org/';
		public $gitUrl = 'git@bitbucket.org:';

		public $repos = array();


		public function __construct() {

			$this->defaultConfig = array(
					"debug"=>"0",
					"accountName"=>"youraccount",
					"pathToPublicKey"=>"~/.ssh/id_rsa.pub",
					"userPass"=>"username:password",
					"logFile"=>"command.log",
					"targetDirectory"=>"/path/to/your/directory/"
				);

			$this->setConfig();
		}

		public function setConfig() {

			if( ! $userConfig = file_get_contents($this->externalOptionsFile)) {
				die('External config file could not be opened. Exiting.....'."\r\n");
			}

			if( ! $userConfig = json_decode($userConfig, TRUE)) {
				die('Bad syntax in external config file. Exiting.....'."\r\n");
			}

			$this->config = array_merge($this->defaultConfig, $userConfig);
		}

		public function getConfig($key = NULL) {

			if(is_null($key)) {
				return $this->config;
			}

			return isset($this->config[$key]) ? $this->config[$key] : NULL;
		}

		public function getRepos() {

			$url = $this->apiUrl.'repositories/'.$this->getConfig('accountName');

			while($url) {
				$curl = curl_init($url);
				curl_setopt($curl, CURLOPT_SSH_PUBLIC_KEYFILE, $this->getConfig('pathToPublicKey'));
				curl_setopt($curl, CURLOPT_USERPWD, $this->getConfig('userPass'));
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
				
				if($this->getConfig('debug')) {
					// verbose output if we are debugging
					//curl_setopt($curl, CURLOPT_VERBOSE, TRUE);
				}				

				$response = curl_exec($curl);
				$repos = json_decode($response, TRUE);

				foreach($repos['values'] as $index=>$repo) {
					$this->repos[] = $repo;			
				}

				// get the next url passed back by bitbucket and continue if the next page is available
				$url = isset($repos['next']) ? $repos['next'] : NULL;

				curl_close($curl);
			}

			echo count($this->repos);
		}

		public function pullDown() {

			$targetDir = $this->getConfig('targetDirectory');
			// add a timestamp to the log file to keep it unique
			$log = $this->getConfig('targetDirectory').time().'_'.$this->getConfig('logFile');
			
			foreach($this->repos as $index=>$repo) {

				// skip git for the time being
				if($repo['scm'] == 'git') {
					
					// if the folder exists (and is not a file), then hg pull and update
					if(file_exists($targetDir.$repo['full_name'])) {
						echo 'chdir('.$targetDir.$repo['full_name'].')';
						chdir($targetDir.$repo['full_name']);
						exec('git pull '.$this->gitUrl.$repo['full_name'].' >> '.$log);
					} 
					// if the folder does not exist, then clone
					else {
						echo 'git clone '.$this->gitUrl.$repo['full_name'].' '.$targetDir.$repo['full_name'].' >> '.$log."\r\n";
						exec('git clone '.$this->gitUrl.$repo['full_name'].' '.$targetDir.$repo['full_name'].' >> '.$log);
					}
				} else {
					// if the folder exists (and is not a file), then hg pull and update
					if(file_exists($targetDir.$repo['full_name'])) {
						//echo 'cd '.$targetDir.$repo['full_name'].' && hg pull '.$this->hgUrl.$repo['full_name'].' > '.$log."\r\n";
						//echo 'chdir('.$targetDir.$repo['full_name'].')';
						chdir($targetDir.$repo['full_name']);
						exec('hg pull '.$this->hgUrl.$repo['full_name'].' >> '.$log);
						exec('hg update');
					} 
					// if the folder does not exist, then clone
					else {
						echo 'hg clone '.$this->hgUrl.$repo['full_name'].' '.$targetDir.$repo['full_name'].' >> '.$log."\r\n";
						exec('hg clone '.$this->hgUrl.$repo['full_name'].' '.$targetDir.$repo['full_name'].' >> '.$log);
					}
				}
			}

			echo "\r\n".'Complete!'."\r\n";
		}

		public function run() {
			
			$this->getRepos();
			$this->pullDown();
		}
	}


	$sync = new bitbucketSync();

	$sync->run();