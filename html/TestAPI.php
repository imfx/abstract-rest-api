<?php
    use net\hdssolutions\api\AbstractAPI;

    final class TestAPI extends AbstractAPI {
        // usuarios de ejemplo
        private $users = null;
        // resultado
        private $result = [];

        public function __construct() {
            $result = null;
            try {
                // enviamos los datos al parent
                parent::__construct();
                $this->users = [
                    (object)[
                        'href' => 'users/' . substr(md5(1),5,10),
                        'name' => 'Uno'
                    ],
                    (object)[
                        'href' => 'users/' . substr(md5(2),5,10),
                        'name' => 'Dos'
                    ],
                    (object)[
                        'href' => 'users/' . substr(md5(3),5,10),
                        'name' => 'Tres'
                    ]
                ];
                // ejecutamos la api
                $result = $this->processAPI();
            } catch (Exception $e) {
                // procesamos la excepcion
                $result = $this->processException($e);
            }
            echo $result;
        }

        protected final function getAppKey($app_id) {
            if ($app_id != 123)
                throw new Exception('Bad authentication data', 401);
            return md5($app_id);
        }

        protected function vardump($verb, $args, $data) {
            if ($this->method !== 'GET' && $this->method !== 'POST' && $this->method !== 'PUT')
                throw new Exception("Only accepts GET, POST & PUT requests", 405);
            $result = [];
            $result['method'] = $this->method;
            $result['verb'] = $verb;
            $result['args'] = $args;
            $result['data'] = $data;
            $result['file'] = $this->file;
            return $result;
        }

        protected function users_get($verb, $args, $data) {
            if ($verb !== null) {
                // bandera
                $found = false;
                // recorremos los usuarios
                foreach ($this->users as $user) {
                    if (substr($user->href, -10) == $verb) {
                        $found = $user;
                        break;
                    }
                }
                if (!$found)
                    throw new Exception('User not found', 404);
                return [ 'success' => true, 'result' => $found ];
            } else
                return [ 'success' => true, 'result' => $this->users ];
        }

        protected function users_post($verb, $args, $data) {
            $this->result['success'] = true;
            $this->result['created'] = true;
            return $this->result;
        }

        protected function users_put($verb, $args, $data) {
            if ($verb !== null)
                throw new Exception('User id must be not especified', 405);
            $this->result['created'] = true;
            // recorremos los datos a actualizar
            foreach ($this->file as $key => $value)
                $this->result[$key] = $value;
            $this->result['success'] = true;
            return $this->result;
        }

        protected function users_delete($verb, $args, $data) {
            // bandera
            $found = false;
            // recorremos los usuarios
            foreach ($this->users as $user) {
                if ($user->id == $verb) {
                    $found = $user;
                    break;
                }
            }
            if (!$found)
                throw new Exception('User not found', 404);
            // recorremos los datos a actualizar
            $this->result['success'] = true;
            $this->result['deleted'] = true;
            return $this->result;
        }

        protected function json_get($verb, $args, $data) {
            return [ 'verb' => $verb, 'args' => $args, 'data' => $data, 'file' => $this->file ];
        }

        protected function json_post($verb, $args, $data) {
            return [ 'verb' => $verb, 'args' => $args, 'data' => $data, 'file' => $this->file ];
        }

        protected function json_put($verb, $args, $data) {
            return [ 'verb' => $verb, 'args' => $args, 'data' => $data, 'file' => $this->file ];
        }
    }