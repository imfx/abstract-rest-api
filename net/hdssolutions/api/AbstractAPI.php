<?php
    namespace net\hdssolutions\api;

    use Exception;
    use ReflectionMethod;

    abstract class AbstractAPI {
        /**
         * The HTTP method this request was made in, eigther GET, POST, PUT or DELETE
         * @var String
         */
        protected $method   = '';

        /**
         * An optional additional descriptor about the endpoint.
         * Used for things that can not be handled by the basic methods. eg: /files/process
         * @var String
         */
        protected $verb     = null;

        /**
         * Stores the input of the PUT request
         * @var Mixed
         */
        protected $file     = [];

        /**
         * Allow PUT & DELETE request without a Verb
         * @var boolean Default false
         */
        protected $allowPutDeleteWithoutVerb = false;

        /**
         * Allow POST request with a Verb
         * @var boolean Default false
         */
        protected $allowPostWithVerb = false;

        /**
         * The model requested in the URI. eg: /files
         * @var String
         */
        private $endpoint   = null;

        /**
         * Any additional URI components after the endpoint and verb have been removed.
         * In our case, an integer ID for the resource. eg: /<endpoint>/<verb>/<arg0>/<arg1> or /<endpoint>/<arg0>
         * @var Mixed[]
         */
        private $args       = [];

        /**
         * Request data
         * @var Array
         */
        private $request    = null;

        /**
         * Auth if exists
         * @var String
         */
        private $auth       = null;

        /**
         * Process time
         * @var float
         */
        private $time       = 0;

        /**
         * Allow for CORS, assemble and pre-process the data
         */
        public function __construct($request = null) {
            // save start time
            $this->time = microtime(true);
            // permitimos conexiones desde cualquier origen
            header('Access-Control-Allow-Orgin: *');
            // permitimos cualquier tipo de metodo
            header('Access-Control-Allow-Methods: *');
            // retornaremos un JSON como resultado
            header('Content-Type: application/json');
            // almacenamos la autenticacion si existe
            $this->auth = isset($_SERVER['HTTP_CONTENT_AUTH']) ? base64_decode($_SERVER['HTTP_CONTENT_AUTH']) : null;
            // en las peticiones desde el mismo servidor (localhost) no tenemos la cabecera HTTP_ORIGIN
            if (!array_key_exists('HTTP_ORIGIN', $_SERVER))
                // almacenamos el nombre del servidor como origen
                $_SERVER['HTTP_ORIGIN'] = $_SERVER['SERVER_NAME'];
            // procesamos los parametros
            $this->args = explode('/', rtrim($request !== null ? $request : (isset($_GET['uri_request']) ? $_GET['uri_request'] : null), '/'));
            // obtenemos el modulo solicitado
            $this->endpoint = array_shift($this->args);
            // verificamos si tenemos el descriptor adicional
            if (array_key_exists(0, $this->args))
                // almacenamos el verbo
                $this->verb = array_shift($this->args);
            // obtenemos el metodo
            $this->method = strtoupper($_SERVER['REQUEST_METHOD']);
            // verificamos si el metodo es POST
            if ($this->method == 'POST' && array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER)) {
                // verificamos si es DELETE
                if (strtoupper($_SERVER['HTTP_X_HTTP_METHOD']) == 'DELETE')
                    $this->method = 'DELETE';
                // verificamos si es PUT
                else if (strtoupper($_SERVER['HTTP_X_HTTP_METHOD']) == 'PUT')
                    $this->method = 'PUT';
                else
                    // retornamos con un error
                    throw new Exception('Unexpected Header', 400);
            }
            // limpiamos el request ya procesado
            unset($_GET['uri_request']);
            // procesamos el metodo
            $this->_method();
        }

        public final function __toString() {
            //
            $request_data = clone $this->request;
            //
            if ($this->method === 'POST' && $this->endpoint === 'login' && isset($request_data->pass))
                //
                $request_data->pass = '********';
            // return request uri
            return
                // METHOD/endpoint/verb
                $this->method.'/'.$this->endpoint.($this->verb !== null ? '/'.$this->verb : '').
                // extra args|endpoints
                (count($this->args) > 0 ? '/'.implode('/', $this->args) : '').
                // GET parameters
                (strlen(http_build_query($this->get_params)) > 0 ? '?'.urldecode(http_build_query($this->get_params)) : '').
                // POST|PUT data
                (in_array($this->method, [ 'POST', 'PUT' ]) ? ' '.json_encode($this->method == 'POST' && (!isset($_SERVER['CONTENT_TYPE']) || !preg_match('/application\/json/', $_SERVER['CONTENT_TYPE'])) ? $request_data : $this->file):'');
        }

        protected function _method() {
            // verificamos el metodo
            switch($this->method) {
                case 'DELETE':
                case 'POST':
                    // capture GET args
                    $this->request = (object)$this->_cleanInputs($_GET);
                    // check for JSON POST data
                    if (isset($_SERVER['CONTENT_TYPE']) && preg_match('/application\/json/', $_SERVER['CONTENT_TYPE'])) {
                        // parse JSON
                        $this->file = json_decode(file_get_contents('php://input'));
                        // check for double escaped JSON
                        if (gettype($this->file) === 'string') $this->file = json_decode($this->file);
                    } else
                        // append POST data to request
                        $this->request = (object)array_merge((array)$this->request, $this->_cleanInputs($_POST));
                    break;
                case 'GET':
                    // capture GET args
                    $this->request = (object)$this->_cleanInputs($_GET);
                    break;
                case 'PUT':
                    // capture GET args
                    $this->request = (object)$this->_cleanInputs($_GET);
                    // check for JSON POST data
                    if (isset($_SERVER['CONTENT_TYPE']) && preg_match('/application\/json/', $_SERVER['CONTENT_TYPE'])) {
                        // parse JSON
                        $this->file = json_decode(file_get_contents('php://input'));
                        // check for double escaped JSON
                        if (gettype($this->file) === 'string') $this->file = json_decode($this->file);
                    } else {
                        // get fields from input
                        parse_str(file_get_contents('php://input'), $this->file);
                        // convert fields to object
                        $this->file = (object)$this->file;
                    }
                    break;
                default:
                    throw new Exception('Invalid Method', 405);
                    break;
            }
            // save GET data (for toString() method)
            $this->get_params = (object)$this->_cleanInputs($_GET);
            // unset request param (made by .htaccess)
            unset($this->get_params->request);
            unset($this->request->request);
            // empty GET, POST y REQUEST
            $_GET = $_POST = $_REQUEST = [];
        }

        protected final function processAPI() {
            // verificamos si se especifico el endpoint
            if (strlen($this->endpoint) === 0)
                // retornamos un error
                throw new Exception('Endpoint not especified', 405);
            // verificamos si existe el endpoint a ejecutar
            if (!method_exists($this, $this->endpoint . '_get') && !method_exists($this, $this->endpoint . '_put') && !method_exists($this, $this->endpoint . '_post') && !method_exists($this, $this->endpoint . '_delete'))
                // retornamos un error
                throw new Exception('No Endpoint: ' . $this->endpoint, 404);
            // verificamos si existe el metodo
            if (!method_exists($this, $this->endpoint . '_' . strtolower($this->method)))
                // retornamos un error
                throw new Exception('Method not allowed: ' . $this->method, 400);
            // verificamos si el metodo es privado
            $reflection = new ReflectionMethod($this, $this->endpoint . '_' . strtolower($this->method));
            if ($reflection->isPrivate())
                // retornamos un error
                throw new Exception('Method not allowed: ' . $this->method . '/' . $this->endpoint, 400);
            // verificamos si es PUT o DELETE y no se especifico el verb
            if (($this->method == 'PUT' || $this->method == 'DELETE') && $this->verb === null && !$this->allowPutDeleteWithoutVerb)
                // salimos con un error
                throw new Exception('Verb not specified', 400);
            // verificamos si es POST y se especifico verb
            if ($this->method == 'POST' && $this->verb !== null && count($this->args) % 2 == 0 && !$this->allowPostWithVerb)
                // salimos con un error
                throw new Exception('Verb must not be specified', 400);
            // ejecutamos el endpoint y obtenemos el resultado
            $result = $this->__call_endpoint($this->endpoint.'_'.strtolower($this->method), $this->verb, $this->args, $this->request);
            // verificamos si es PUT y no hay cambios
            if ($this->method == 'PUT' && $result === 304)
                // retornamos sin cambios
                return $this->_response((object)[
                        'success'   => true,
                        'code'      => $result,
                        'time'      => round((microtime(true) - $this->time) * 1000)
                    ]);
            else
                // ejecutamos el metodo y retornamos el resultado
                return $this->_response((object)array_merge([
                        'success'   => isset($result->success) ? $result->success : false,
                        'code'      => (count($result) ? ($this->method === 'POST' ? 201 : 200) : 204),
                        'time'      => round((microtime(true) - $this->time) * 1000)
                    ], $result));
        }

        protected function __call_endpoint($endpoint, $verb, $args, $data, $local = false) {
            // execute the endpoint by default
            return $this->$endpoint($verb, $args, $data, $local);
        }

        protected final function paginationLimit() {
            // obtenemos count
            $count = isset($this->request->count) ? $this->request->count : 50;
            // obtenemos el offset (page * limit)
            $offset = isset($this->request->page) ? $this->request->page * $count : 0;
            // armamos la clausula LIMIT
            return " LIMIT $offset, $count";
        }

        protected final function processException($e) {
            // retornamos el mensaje de error
            return $this->_response((object)[
                    'success'   => false,
                    'code'      => $e->getCode() != null ? $e->getCode() : 500,
                    'error'     => $e->getMessage()
                ], $e->getCode() != null ? $e->getCode() : 500);
        }

        /**
         * Retorna true si se solicito que el campo se expanda
         * @param String $source Endpoint de origen
         * @param String $field Campo a verificar
         * @param String $expand Variables a expandir
         * @return boolean True si el campo esta en la lista solicitada
         */
        protected final function expand($source, $field, $expand = null) {
            // verificamos si se especifico expand, sino lo obtenemos desde el request
            $expand = $expand !== null ? $expand : (isset($this->request->expand) ? $this->request->expand : '');
            // recorremos los campos a expandir
            foreach (explode(',', $expand) as $expandable) {
                // separamos source.field
                list($sExpandable, $fExpandable) = array_pad(explode('.', $expandable, 2), 2, null);
                // verificamos si no tenemos field
                if ($fExpandable == null || strlen($fExpandable) == 0) {
                    // invertimos las variables
                    $fExpandable = $sExpandable;
                    // almacenamos $source
                    $sExpandable = $source;
                }
                // retornamos si es el campo
                if ($sExpandable == $source && ($fExpandable == $field || $fExpandable == '*'))
                    // retornamos true
                    return true;
            }
            // retornamos false
            return false;
        }

        protected final function consume($source, &$data) {
            //
            $expand = explode(',', $data->expand);
            //
            foreach ($expand as $idx => $expandable) {
                //
                if (preg_match('/^'.$source.'\./', $expandable)) {
                    //
                    unset($expand[$idx]);
                    //
                    $data->expand = implode(',', $expand);
                    //
                    return true;
                }
            }
            //
            return false;
        }

        protected function _response($data, $status = 200) {
            // add empty error attr if not exists
            if (!isset($data->error)) $data->error = null;
            // convert to json
            $data = json_encode($data);
            // add headers
            header($_SERVER['SERVER_PROTOCOL'] . ' ' . $status . ' ' . $this->_requestStatus($status));
            header('Content-Length: ' . strlen($data));
            // return the JSON result
            return $data;
        }

        private function _cleanInputs($data) {
            // eliminamos los espacios en blanco de cada parametro recibido
            $clean_input = [];
            if (is_array($data))
                foreach ($data as $k => $v)
                    $clean_input[$k] = $this->_cleanInputs($v);
            else
                $clean_input = trim($data);
            // retornamos los parametros limpios
            return $clean_input;
        }

        protected final function _requestStatus($code) {
            // lista de estados posibles
            $status = [
                200 => 'OK',
                201 => 'Created',
                204 => 'No Content',
                304 => 'Not Modified',
                400 => 'Bad Request',
                401 => 'Unauthorized',
                403 => 'Forbidden',
                404 => 'Not Found',
                405 => 'Method Not Allowed',
                409 => 'Conflict',
                500 => 'Internal Server Error',
            ];
            // retornamos el mensaje del estado
            return isset($status[$code]) ? $status[$code] : $status[500];
        }
    }