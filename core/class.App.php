<?php
/***********************************************************************
************************************************************************
	Class App
	Manage Applications
************************************************************************
***********************************************************************/

class App extends MVC
{
	private static $instances;
	public $url;
	public $NEMESIS = null;
	public $forbiddenMethods = array('setup', 'run', 'setAsDefault', 'startTime', 'endTime');
	
	public function __construct($name, $version, $url)
	{
		$this->name = $name;
		$this->version = $version;
		$this->path = APPS.$this->name.'/';
		$this->resources_url = NEMESIS_URL.'apps/'.$this->name.'/resources/';
		$this->url = strtolower((($url)? $url:$this->name));
		URL::$prefix = trim($this->url, '/').'/';
		$this->NEMESIS = Loader::getInstance();
	}
	
	public function setAsDefault()
	{
		$this->url = '';
		URL::$prefix = '';
	}
	
	public function startTime()
	{
		$time = microtime();
		$time = explode(' ', $time);
		$time = $time[1] + $time[0];
		$this->startTime = $time;
	}
	
	public function endTime()
	{
		$time = microtime();
		$time = explode(' ', $time);
		$time = $time[1] + $time[0];
		$finish = $time;
		$total_time = round(($finish - $this->startTime), 4);
		echo '<br />Page generated in '.$total_time.' seconds.';
	}
	
	public function setup()
	{
	}
	
	public function run()
	{
		$this->setup();
		
		// detect if the current URL calls the app
				
		Hook::get('App', 'FILENAME')->apply($this);
		
		if (is_file(NEMESIS_PATH.URL::$request['SOURCE']) && file_exists(NEMESIS_PATH.URL::$request['SOURCE']))
		{
			header('Content-type: '.mime_content_type(NEMESIS_PATH.URL::$request['SOURCE']));
			echo file_get_contents(NEMESIS_PATH.URL::$request['SOURCE']);
			exit();
		}
		
		$request = URL::$request['SOURCE'];
	
		if (empty($request) && !empty($this->url))
			return false;
		
		if (!empty($request) && !empty($this->url))
		{
		
			if (strpos($request, $this->url) !== 1)
				return false;
			
			$request = str_replace('/'.$this->url, '', $request);	
		}
		
		URL::$request['HASH'] = explode('/', trim($request, '/'));
		
		// load the page method		
		if (empty($request))
		{
			Hook::get('App', 'URL')->apply($this, 'index');
			$this->index();
		}
		else
		{
			$method = array_shift(URL::$request['HASH']);
			Hook::get('App', 'URL')->apply($this, $method);
			
			if (method_exists($this->name, $method) && !in_array(strtolower($method), $this->forbiddenMethods))
			{
				$this->$method(URL::$request['HASH']);
			}
			else if ($file=$this->getController($method))
			{
				ob_start();
				$HASH = &URL::$request['HASH'];
				$MVC = &$this;
				include_once($file);
				$this->injectCol('html', 'controller', ob_get_clean());
				$this->addToBuffer($this->getView('html'));
			}
			else
			{
				$this->error404(URL::$request['HASH']);
			}

		}
		
	}
	
	public static function getInstance($name, $version='1', $url='')
	{
		if (!isset(self::$instances[$name]))
		{
			if (file_exists($config=APPS.$name.'/config.php'))
				require_once($config);
			
			if (file_exists($functions=APPS.$name.'/functions.php'))
				require_once($functions);
		
			if (!file_exists($file=APPS.$name.'/app.php'))
			{
				echo $file.' is missing. Cannot run application called '.$name.'';
				die;
			}
			
			require_once($file);
			
			self::$instances[$name] = new $name($name, $version, $url);
		}
		return self::$instances[$name];
	}
	
	public function index()
	{
		$this->addTobuffer('HOME PAGE');
	}
	
	public function error404($arguments=array()) 
	{
		if ($file=$this->getController('error404'))
		{
			ob_start();
			$HASH = &URL::$request['HASH'];
			$MVC = &$this;
			include_once($file);
			$this->injectCol('html', 'controller', ob_get_clean());
			$this->addToBuffer($this->getView('html'));
			return false;
		}
		else
			$this->addTobuffer('ERROR 404 : PAGE NOT FOUND');
	}
}