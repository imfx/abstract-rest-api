<?php
    namespace net\hdssolutions\api;

    require_once __DIR__.'/AbstractAPI.php';

    use \Exception;
    use net\hdssolutions\api\AbstractAPI;

    abstract class AbstractSecureAPI extends AbstractAPI {
        /**
         * Clave para desencriptar las peticiones
         */
        private $app_key = null;

        protected abstract function getAppKey($app_data);

        protected final function _method() {
            // verificamos si es una peticion valida
            if (!isset($_REQUEST['app_id']) || !isset($_REQUEST['enc_request']))
                // salimos con una excepcion
                throw new Exception('Request is not valid', 400);
            // obtenemos la clave de la app (MD5)
            $this->app_key = $this->getAppKey($_REQUEST['app_id']);
            // desencriptamos la peticion
            if (isset($_GET['enc_request']))
                $_GET  = json_decode(trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $this->app_key, base64_decode($_GET['enc_request']), MCRYPT_MODE_ECB)));
            if (isset($_POST['enc_request']))
                $_POST = json_decode(trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $this->app_key, base64_decode($_POST['enc_request']), MCRYPT_MODE_ECB)));
            $_REQUEST  = json_decode(trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $this->app_key, base64_decode($_REQUEST['enc_request']), MCRYPT_MODE_ECB)));
            // procesamos el original
            parent::_method();
        }

        protected final function _response($data, $status = 200) {
            // generamos el header
            header($_SERVER['SERVER_PROTOCOL'] . ' ' . $status . ' ' . $this->_requestStatus($status));
            // ciframos el resultado
            $data->result = $data->result !== null ? base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $this->app_key, json_encode($data->result), MCRYPT_MODE_ECB)) : null;
            // retornamos el resultado JSON
            return json_encode($data);
        }
    }