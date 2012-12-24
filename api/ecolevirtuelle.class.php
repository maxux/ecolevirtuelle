<?php
class EcoleVirtuelle {
	var $token    = null;
	var $cookie   = null;
	var $debuglvl = 2;
	var $reqid    = 0;
	
	var $data     = array('blocks'     => array(1 => array(), 2 => array(), 3 => array(), 4 => array()),
	                      'username'   => null,
	                      'newmessage' => -1,
	                      );

	var $months   = array('janvier'   => 1,
	                      'février'   => 2,
	                      'mars'      => 3,
	                      'avril'     => 4,
	                      'mai'       => 5,
	                      'juin'      => 6,
	                      'juillet'   => 7,
	                      'août'      => 8,
	                      'septembre' => 9,
	                      'octobre'   => 10,
	                      'novembre'  => 11,
	                      'décembre'  => 12
	                      );
	
	function __construct($login, $pass, $cookie = null) {
		if(!$cookie) {
			$this->cookie = '/tmp/ev.cookie.'.rand();
			$this->debug('Cookie file: '.$this->cookie);
			
			$this->loadtoken();
			
			$x = $this->dlrequest('https://ecolevirtuelle.provincedeliege.be/public/ecov.entree_gestion.actionConnexion?p_temp=1',
					      array('p_username' => $login,
						    'p_password' => $pass,
						    'p_site2pstoretoken' => $this->token,
						    'p_bt.x' => '0',
						    'p_bt.y' => '0')
					);

			$x = $this->dlrequest('https://sso.ecolevirtuelle.provincedeliege.be/pls/orasso/orasso.wwsso_app_admin.ls_login',
					      array('ssousername' => $login,
						    'password' => $pass,
						    'site2pstoretoken' => $this->token)
					);
			
			$this->parsehome($x);
			
		} else $this->cookie = $cookie;
	}
	
	function debug($message) {
		if($this->debuglvl < 1)
			return;
		
		echo "[+] $message\n";
	}
	
	function dlrequest($url, $data = NULL) {
		$this->debug('Loading: request #'.$this->reqid);

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);			
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64; EcoleVirtuelle API; n00bz)');
		curl_setopt($ch, CURLOPT_SSLVERSION,3); 
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); 
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 20);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie);
		
		if($this->debuglvl > 1)
			curl_setopt($ch, CURLOPT_VERBOSE, true);
			
		if($data) {
			curl_setopt($ch, CURLOPT_POST, true);
			
			foreach($data as $key => $value)
				$temp[] = $key.'='.urlencode($value);
				
			curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $temp));
		}

		$data = curl_exec($ch);
		curl_close($ch);

		return $data;
	}
	
	function loadtoken() {
		$this->debug('Loading token');
		
		$x = $this->dlrequest('https://ecolevirtuelle.provincedeliege.be/');

		$xmldoc = new DOMDocument();
		$xmldoc->loadHTML($x);
		$xpathvar = new Domxpath($xmldoc);
		$queryResult = $xpathvar->query('//input');

		foreach($queryResult as $result) {
			if($result->getAttribute('name') == 'p_site2pstoretoken') {
				$this->token = $result->getAttribute('value');
				$this->debug('Token: '.$this->token);
			}
		}
	}
	
	function __set_header($queryResult) {
		$index = 0;
		
		foreach($queryResult as $result) {
			// Messages
			if($index == 0) {
				if(trim($result->nodeValue) == 'Pas de messages') {
					$this->debug('Message: no new messages');
					$this->data['newmessage'] = 0;
				
				// FIXME
				} else $this->data['newmessage'] = 42;
			
			// Profile
			} else if($index == 1) {
				$this->data['username'] = trim(preg_replace('/Mon profil \((.+)\)/', '$1', $result->nodeValue));
				$this->debug('Username: '.$this->data['username']);
			
			// Disconnect	
			} else return;
			
			$index++;
		}
	}
	
	function __home_date($str) {
		$temp = preg_split('/([ ]+)/', $str);
		return mktime(0, 0, 0, $this->months[$temp[2]], $temp[1], $temp[3]);
	}
	
	function __set_blocks($xpathvar) {
		foreach($this->data['blocks'] as $id => $data) {
			$block = 'div[@id="block_news_'.$id.'"]/div[@class="postit"]';
			
			// date, owner
			$sub   = 'div[@class="postitTitle"]/div[@class="postitInfo"]';
			$queryResult = $xpathvar->query('//'.$block.'/'.$sub);
		
			$index = 0;
			foreach($queryResult as $result) {
				$data  = trim($result->nodeValue);
				$owner = &$this->data['blocks'][$id][$index]['owner'];
				$date  = &$this->data['blocks'][$id][$index]['date'];
				
				$owner = preg_replace('/(.+)  2([0-9]+)(.+)/', '$3', $data);
				$date  = $this->__home_date(substr($data, 0, strlen($data) - strlen($owner)));
				
				$index++;
			}
			
			// TODO: attachment
			
			// content
			$sub   = 'div[@class="postitBody"]';
			$queryResult = $xpathvar->query('//'.$block.'/'.$sub);
		
			$index = 0;
			foreach($queryResult as $result) {
				// shortcut message
				$this->data['blocks'][$id][$index]['message'] = null;
				$msg = &$this->data['blocks'][$id][$index]['message'];
				
				$did = substr($result->parentNode->getAttribute('id'), 5);
				$msg = trim($result->nodeValue);
				
				// if extend exists
				$extend = $xpathvar->query('//'.$block.'/div[@id="news_body_expand_'.$did.'"]');
				if($extend->length)
					$msg = 	substr($msg, 0, strlen($msg) - 3).trim($extend->item(0)->nodeValue);
				
				$index++;
			}
		}
		
		print_r($this->data['blocks']);
	}
	
	function parsehome($html) {
		$xmldoc = new DOMDocument();
		$xmldoc->loadHTML($html);
		$xpathvar = new Domxpath($xmldoc);
		
		/* header (messages, profile, disconnect) */
		$this->__set_header($xpathvar->query('//div[@id="header"]/ul/li'));
		
		/* blocks (away, general notes, section notes) */
		$this->__set_blocks($xpathvar);
	}
}

$ev = new EcoleVirtuelle('MAXIME DANIEL', '', '/tmp/ev.cookie.1611080879');
$x = $ev->dlrequest('https://ecolevirtuelle.provincedeliege.be/myecov/ecov.accueil_gestion.Accueil');
$ev->parsehome($x);

echo json_encode($ev->data, JSON_PRETTY_PRINT);
?>

