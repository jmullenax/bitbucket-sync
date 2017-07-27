<?php

	class bitbucketSync {

		private static $externalOptionsFile = 'config.json';
		
		private static $apiUrl = 'https://bitbucket.org/api/2.0/';
		private static $hgUrl = 'ssh://hg@bitbucket.org/';
		private static $gitUrl = 'git@bitbucket.org:';

		public $repos = array();
		public $config = array();

		public function __construct() {

			

			$this->setConfig();
		}

		public function getDefaultConfig() {
			return $this->defaultConfig = array(
					"debug"=>"0",
					"accountName"=>"youraccount",
					"pathToPublicKey"=>"~/.ssh/id_rsa.pub",
					"userPass"=>"username:password",
					"logFile"=>"command.log",
					"targetDirectory"=>"/path/to/your/directory/"
				);
		}

		public function getOptions() {
			
			$ret = [];

			$opts = getopt('d::a::u::p::P::l::t::', ['debug::', 'accountName::', 'pathToPublicKey::', 'user::', 'pass::', 'logFile::', 'targetDirectory::']);

			$optMap = [
				'd' => 'debug',
				'a' => 'accountName',
				'u' => 'user',
				'p' => 'password',
				'P' => 'pathToPublicKey',
				'l' => 'logFile',
				't' => 'targetDirectory'
			];

			$shortOpts = array_keys($optMap);
			$longOpts = array_values($optMap);

			foreach ($opts as $key => $opt) {
				
				if(in_array($key, $shortOpts, TRUE)) {
					$ret[$optMap[$key]] = $opt;
				}

				if(in_array($key, $longOpts, TRUE)) {
					$ret[$key] = $opt;
				}

			}

			die(var_dump($ret));

			return $ret;
		}

		public function setConfig() {

			if( ! $userConfig = file_get_contents(self::$externalOptionsFile)) {
				die('External config file could not be opened. Exiting.....'."\r\n");
			}

			if( ! $userConfig = json_decode($userConfig, TRUE)) {
				die('Bad syntax in external config file. Exiting.....'."\r\n");
			}

			$this->config = array_merge($this->getDefaultConfig(), $userConfig, $this->getOptions());
		}

		public function getConfig($key = NULL) {

			if(is_null($key)) {
				return $this->config;
			}

			return isset($this->config[$key]) ? $this->config[$key] : NULL;
		}

		public function getRepos() {

			$url = self::$apiUrl.'repositories/'.$this->getConfig('accountName');

			while($url) {
				$curl = curl_init($url);
				curl_setopt($curl, CURLOPT_SSH_PUBLIC_KEYFILE, $this->getConfig('pathToPublicKey'));
				curl_setopt($curl, CURLOPT_USERPWD, $this->getConfig('userPass'));
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
				
				if($this->getConfig('debug')) {
					// verbose output if we are debugging
					curl_setopt($curl, CURLOPT_VERBOSE, TRUE);
				}				

				$response = curl_exec($curl);

				if($e = curl_error($curl)) {
					die($e);
				}

				$repos = json_decode($response, TRUE);

				foreach($repos['values'] as $index=>$repo) {
					$this->repos[] = $repo;			
				}

				// get the next url passed back by bitbucket and continue if the next page is available
				$url = isset($repos['next']) ? $repos['next'] : NULL;

				curl_close($curl);
			}

			if($this->getConfig('debug')) {
				echo 'Found '.count($this->repos).' repo(s)...'."\r\n";
			}
			
		}

		public function pullDown() {

			$targetDir = $this->getConfig('targetDirectory');
			// add a timestamp to the log file to keep it unique
			$log = $this->getConfig('targetDirectory').time().'_'.$this->getConfig('logFile');
			
			foreach($this->repos as $index=>$repo) {

				if($repo['scm'] == 'git') {
					$type = 'git';
					$repoType = self::$gitUrl;
				} else {
					$type = 'hg';
					$repoType = self::$hgUrl;
				}


				// if the folder exists (and is not a file), then pull and update
				if(file_exists($targetDir.$repo['full_name'])) {

					if($this->getConfig('logFile')) {
						echo 'chdir('.$targetDir.$repo['full_name'].')';
					}
					
					chdir($targetDir.$repo['full_name']);

					if($this->getConfig('logFile')) {
						echo $type.' pull '.$repoType.$repo['full_name'].' >> '.$log;
					}

					exec($type.' pull '.$repoType.$repo['full_name'].' >> '.$log);
				} 
				// if the folder does not exist, then clone
				else {

					if($this->getConfig('logFile')) {
						echo $type.' clone '.$repoType.$repo['full_name'].' '.$targetDir.$repo['full_name'].' >> '.$log."\r\n";
					}

					exec($type.' clone '.$repoType.$repo['full_name'].' '.$targetDir.$repo['full_name'].' >> '.$log);
				}

				/*// skip git for the time being
				if($repo['scm'] == 'git') {
					
					
				} else {
					// if the folder exists (and is not a file), then hg pull and update
					if(file_exists($targetDir.$repo['full_name'])) {
						//echo 'cd '.$targetDir.$repo['full_name'].' && hg pull '.$this->hgUrl.$repo['full_name'].' > '.$log."\r\n";
						//echo 'chdir('.$targetDir.$repo['full_name'].')';
						chdir($targetDir.$repo['full_name']);
						exec('hg pull '.self::$hgUrl.$repo['full_name'].' >> '.$log);
						exec('hg update');
					} 
					// if the folder does not exist, then clone
					else {
						echo 'hg clone '.self::$hgUrl.$repo['full_name'].' '.$targetDir.$repo['full_name'].' >> '.$log."\r\n";
						exec('hg clone '.self::$hgUrl.$repo['full_name'].' '.$targetDir.$repo['full_name'].' >> '.$log);
					}
				}*/
			}

			echo "\r\n".'Complete!'."\r\n";
		}

		public function run() {
			
			$this->getRepos();
			$this->pullDown();
		}
	}

	(new bitbucketSync())->run();