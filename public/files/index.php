<?php
function login_required($ctx){
    if (isset($_SESSION['info'])) {
        $path = $ctx->request_path;
        $info = $_SESSION['info'];
        if (substr($path,  0, strlen($info['url'])) === $info['url']) {
            return;
        }
    }
    header("Location: {$ctx->login_url}?file={$ctx->request_path}", true, 301);
    exit;
}

function authenticate($ctx) {
    if (!isset($_POST['password'])) {
        return null;
    }
    $path = $ctx->request_file; 
    $pwd = $_POST['password'];

    $result = array_filter($ctx->passwords, function($item) use (&$pwd, &$path) {
        if(count($item) > 1 ){
            $base = dirname($path); 
            if (strpos($item[0], $path) !== false || (strpos($item[0], $base) !== false) ){
                return (strpos($item[1], $pwd) !== false);
            }
        }
        return false;
    });

    $var = current($result);
    if ($var === false) {
        return null;
    }
    $url = trim($var[0]);
    return array('url' => $url, 'redir' => $url);
}


function download($abspathname) {
    $filename = basename($abspathname);
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($abspathname)); //Absolute URL
    
    ob_clean();
    flush();
    readfile($abspathname);
}

function http404($ctx) {
    http_response_code(404);
    include $ctx->template_404;
}

function render_html($abspath){
    include $abspath;
}

function getTargets($data_abspathname) {
    $path = $data_abspathname ;
    $files = array(
        $path, "{$path}/index.html"
    );
    return $files;
}

function process($ctx) {
    $files = getTargets($ctx->data_abspathname);

    foreach($files as $file) {
        if (is_file($file)) {
            if (preg_match('/\.html$/', $file)) {
                render_html($file);
            } else {
                download($file);
            }
            return;
        }
    }
    http404($ctx);
}

function init() {
    session_start();

    $login_url = '/files/login.html';

    $request_uri = parse_url($_SERVER['REQUEST_URI']);
    $request_path = $request_uri['path'];
    $request_file = isset($_GET['file']) ? $_GET['file'] : '';
    $script_name = $_SERVER['SCRIPT_NAME'];
    $document_root = $_SERVER['DOCUMENT_ROOT'];
    $base = dirname($script_name);
    $app_dir = dirname(dirname("{$document_root}{$base}"));
    $data_dir = "{$app_dir}/data";
    $template_dir = "{$app_dir}/templates";
    $password_file = "{$app_dir}/.password";
    $passwords = array_map('str_getcsv',file($password_file));
    $data_pathname = str_replace($base, '', $request_path);
    $data_abspathname = "{$data_dir}{$data_pathname}";

    $template_login = "{$template_dir}/login.html";
    $template_404 = "{$template_dir}/404.html";

    return (object)get_defined_vars();
}

function login_controller($ctx) {
    $cred = authenticate($ctx);

    if($cred !== null) { 
        $_SESSION['info'] = $cred;
        $location = "Location: {$cred['url']}";
        header($location, true, 301); 
        return;
    }

    include $ctx->template_login;
}

function data_controller($ctx) {
    login_required($ctx);
    process($ctx);
}

function dispatch() {
    $ctx = init();

    $routes = array(
        '/^\/files\/login.html$/' => function() use(&$ctx){login_controller($ctx);},
        '/^\/files.+/' => function() use(&$ctx){data_controller($ctx);});
        
    foreach($routes as $pattern => $func) {
        if(preg_match($pattern, $ctx->request_path)){
            return $func();
        }
    }
}

dispatch();
?>
