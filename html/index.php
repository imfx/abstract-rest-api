<?php
    error_reporting(E_ALL);
    ini_set('display_errors', true);

    // cargamos la libreria API
    include_once '../net/hdssolutions/api/AbstractAPI.php';
    //include_once '../net/hdssolutions/api/AbstractSecureAPI.php';
    include_once 'TestAPI.php';

    new TestAPI();