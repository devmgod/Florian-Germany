<?php
/**
* Session class
* Author by Muhamad Deva Arofi
*/
 /**
  * decrypt AES 256
  *
  * @param string $edata
  * @param string $password
  * @return decrypted data
  */
function decrypt($edata, $password) {
    $data = base64_decode($edata);
    $salt = substr($data, 0, 16);
    $ct = substr($data, 16);

    $rounds = 3; // depends on key length
    $data00 = $password.$salt;
    $hash = array();
    $hash[0] = hash('sha256', $data00, true);
    $result = $hash[0];
    for ($i = 1; $i < $rounds; $i++) {
        $hash[$i] = hash('sha256', $hash[$i - 1].$data00, true);
        $result .= $hash[$i];
    }
    $key = substr($result, 0, 32);
    $iv  = substr($result, 32,16);

    return openssl_decrypt($ct, 'AES-256-CBC', $key, true, $iv);
  }

/**
 * crypt AES 256
 *
 * @param string $data
 * @param string $password
 * @return base64 encrypted data
 */
function encrypt($data, $password) {
    // Set a random salt
    $salt = openssl_random_pseudo_bytes(16);

    $salted = '';
    $dx = '';
    // Salt the key(32) and iv(16) = 48
    while (strlen($salted) < 48) {
      $dx = hash('sha256', $dx.$password.$salt, true);
      $salted .= $dx;
    }

    $key = substr($salted, 0, 32);
    $iv  = substr($salted, 32,16);

    $encrypted_data = openssl_encrypt($data, 'AES-256-CBC', $key, true, $iv);
    return base64_encode($salt . $encrypted_data);
}
class SessionRaw{
    private $data = [];
    public function data($key, $value = NULL)
    {
        if(is_object($value) || is_array($value)) $value = serialize($value);
        
        if($value != NULL){
            $this->data[$key] = $value;
        }else{
            return isset($this->data[$key]) ? (
                    (is_object($this->data[$key]) || is_array($this->data[$key])) ? 
                        unserialize($this->data[$key]) : $this->data[$key]
                ) : NULL;
        }
    }
    public function destroy($key)
    {
        if (isset($this->data[$key]))
            unset($this->data[$key]);
        return TRUE;
    }
}
class DsSessionHandler extends SessionHandler
{

	public static $handler = null;
    private $key;
	public function __construct($savePath = null, $key = null)
    {
        if (null === $savePath) {
            $savePath = ini_get('session.save_path');
        }

        $baseDir = $savePath;

        if ($count = substr_count($savePath, ';')) {
            if ($count > 2) {
                throw new \InvalidArgumentException(sprintf('Invalid argument $savePath \'%s\'', $savePath));
            }

            // characters after last ';' are the path
            $baseDir = ltrim(strrchr($savePath, ';'), ';');
        }

        if ($baseDir && !is_dir($baseDir) && !@mkdir($baseDir, 0777, true) && !is_dir($baseDir)) {
            throw new \RuntimeException(sprintf('Session Storage was not able to create directory "%s"', $baseDir));
        }
        $this->key = $key;

        ini_set('session.save_path', $savePath);
        ini_set('session.save_handler', 'files');
    }

    private function getSession()
    {
        $sessionRaw = parent::read(session_id());
        if($sessionRaw == ''){
            $sessionObject = new SessionRaw();
        }else{
            $sessionObject = unserialize($sessionRaw);
        }
        return $sessionObject;
    }

    public function read($id)
    {
        parent::read($id);
        $sessionObject = $this->getSession();
        
        $data = $sessionObject->data($id);

        if (!$data) {
            return STRING_EMPTY;
        } else {
            return decrypt($data, $this->key);
        }
    }

    public function write($id, $data)
    {
        $sessionObject = $this->getSession();
        $data = encrypt($data, $this->key);

        $sessionObject->data($id, $data);

        parent::write(session_id(), serialize($sessionObject));
        return true;
    }

    public function destroy($id)
    {
        $sessionObject = $this->getSession();
        $sessionObject->destroy($id);
        parent::write(session_id(), serialize($sessionObject));
        return true;
    }
}

function session(...$params)
{
	if (isset($params[1])) {
		$_SESSION[$params[0]] = $params[1];
	}else{
        $data = $_SESSION[$params[0]] ?? '';
		return $data ?? NULL;
	}
}
function unsession($__key)
{
	DsSessionHandler::$handler->destroy($__key);
}
function set_flash($key, $value)
{
    session($key, $value);
}
function flash($key)
{
    $value = session($key);
    unsession($key);
    return $value;
}