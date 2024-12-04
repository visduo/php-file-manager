<?php
// 错误级别
error_reporting(E_ERROR);
// 中文编码
setlocale(LC_ALL,"zh_CN.UTF-8");
// 开启会话
session_start();

// 网站标题
$web_title = "OSS File Manager";

// 授权用户名和密码
$user_account = "root";
$user_password = "123456";

// 隐藏文件/文件夹
$hidden_file_patterns = ["*.php", ".git", "*.sh"];

// 允许上传的文件类型
$allowed_upload_patterns = ["*.png", "*.jpg", "*.gif", "*.txt", "*.pdf"];

// 获取要操作的文件/文件夹
function get_target() {
    global $hidden_file_patterns;
    
    $target = $_REQUEST["_target"] ?: ".";
    $tmp_directory = dirname($_SERVER["SCRIPT_FILENAME"]);
    if(DIRECTORY_SEPARATOR === "\\") {
        $tmp_directory = str_replace("/",DIRECTORY_SEPARATOR, $tmp_directory);
    }
    $tmp_absolute_path = get_absolute_path($tmp_directory."/".$target);
    
    if(!$tmp_absolute_path) {
        echo "<script>alert(\"非法路径\");window.location.href = \"?\";</script>";
        exit();
    } else if(substr($tmp_absolute_path, 0, strlen($tmp_directory)) !== $tmp_directory) {
        echo "<script>alert(\"非法路径\");window.location.href = \"?\";</script>";
        exit();
    } else if(strpos($target, DIRECTORY_SEPARATOR) === 0) {
        echo "<script>alert(\"非法路径\");window.location.href = \"?\";</script>";
        exit();
    } else if(preg_match("@^.+://@", $target)) {
        echo "<script>alert(\"非法路径\");window.location.href = \"?\";</script>";
        exit();
    }
    
    $target_paths = explode("/", $target);
    for($i = 0; $i < count($target_paths); $i++) {
        if (is_entry_ignored($target_paths[$i], $hidden_file_patterns)) {
            echo "<script>alert(\"非法路径\");window.location.href = \"?\";</script>";
            exit();
        }
    }
    
    return $target;
}

// 列出文件/文件夹列表
function ls($target) {
    global $hidden_file_patterns;
    
    $result = [];
    
    if (is_dir($target) && !is_entry_ignored($target, $hidden_file_patterns)) {
        $files = array_diff(scandir($target), [".", ".."]);
        
        foreach ($files as $entry) {
            if (!is_entry_ignored($entry, $hidden_file_patterns)) {
                $i = $target . "/" . $entry;
                $stat = stat($i);
                $result[] = [
                    "mtime" => $stat["mtime"],
                    "size" => $stat["size"],
                    "name" => basename($i),
                    "path" => preg_replace("@^\./@", "", $i),
                    "is_dir" => is_dir($i)
                ];
            }
        }
        
        usort($result, function ($f1, $f2) {
            $f1_key = ($f1["is_dir"] ? : 2).$f1["name"];
            $f2_key = ($f2["is_dir"] ? : 2).$f2["name"];
            return $f1_key > $f2_key;
        });
    }
    
    return $result;
}

// 获取绝对路径
function get_absolute_path($path) {
    $path = str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $path);
    $parts = explode(DIRECTORY_SEPARATOR, $path);
    $absolutes = [];
    foreach ($parts as $part) {
        if ("." == $part) continue;
        if (".." == $part) {
            array_pop($absolutes);
        } else {
            $absolutes[] = $part;
        }
    }
    return implode(DIRECTORY_SEPARATOR, $absolutes);
}

// 是否忽略文件/文件夹
function is_entry_ignored($entry, $hidden_file_patterns) {
    if ($entry === basename(__FILE__)) {
        return true;
    }
    
    foreach($hidden_file_patterns as $pattern) {
        if(fnmatch($pattern, $entry)) {
            return true;
        }
    }
    return false;
}

// 格式化文件/文件夹大小
function format_size($size) {
    $units = ["B", "KB", "MB", "GB", "TB"];
    $i = 0;
    while ($size >= 1024) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . " " . $units[$i];
}

// 格式化文件/文件夹修改时间
function format_mtime($mtime) {
    return date("Y-m-d H:i:s", $mtime);
}

// 检查文件类型
function check_file_type($name) {
    global $allowed_upload_patterns;
    foreach ($allowed_upload_patterns as $pattern) {
        if (fnmatch($pattern, $name)) {
            return true;
        }
    }
    return false;
}

// 生成uuid
function uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000,
        mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}

// 新建文件夹
function imkdir($target, $name) {
    $name = str_replace("/", "", $name);
    if(substr($name, 0, 2) === "..") {
        echo "<script>alert(\"非法路径\");window.location.href = \"?_target=$target\";</script>";
        exit();
    }
    chdir($target);
    @mkdir($name);
    echo "<script>alert(\"新建成功\");window.location.href = \"?_target=$target\";</script>";
    exit();
}

// 删除文件/文件夹
function rmrf($target, $name) {
    if(is_dir($name)) {
        $files = array_diff(scandir($target."/".$name), ['.','..']);
        foreach ($files as $file) {
            rmrf("$target/$name/$file");
        }
        rmdir($target."/".$name);
    } else {
        unlink($target."/".$name);
    }
    echo "<script>alert(\"删除成功\");window.location.href = \"?_target=$target\";</script>";
    exit();
}

// 上传文件
function upload($target, $file) {
    if(!check_file_type($file['name'])){
        echo "<script>alert(\"非法格式\");window.location.href = \"?_target=$target\";</script>";
        exit();
    }
    
    $ext = pathinfo($file["name"], PATHINFO_EXTENSION);
    move_uploaded_file($file["tmp_name"], $target."/".uuid().".".$ext);
    echo "<script>alert(\"上传成功\");window.location.href = \"?_target=$target\";</script>";
    exit();
}

// 重命名文件/文件夹
function irename($target, $oldname, $newname) {
    rename($target."/".$oldname, $target."/".$newname);
    echo "<script>alert(\"重命名成功\");window.location.href = \"?_target=$target\";</script>";
    exit();
}

// 下载文件
function download($path) {
    $filename = basename($path);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    header('Content-Type: ' . finfo_file($finfo, $path));
    header('Content-Length: '. filesize($path));
    header(sprintf('Content-Disposition: attachment; filename=%s',
        strpos('MSIE',$_SERVER['HTTP_REFERER']) ? rawurlencode($filename) : "\"$filename\"" ));
    ob_flush();
    readfile($path);
    exit;
}

if($_REQUEST["_action"] === "signin") {
    // 登录操作
    if($_POST["_userAccount"] === $user_account && $_POST["_userPassword"] === $user_password) {
        $_SESSION["_user_signin"] = true;
        header("Location: ?");
    } else {
        echo "<script>alert(\"账户账号或账户密码错误\");window.location.href = \"?\";</script>";
        exit();
    }
} else if(!$_SESSION["_user_signin"] && !empty($_REQUEST)) {
    // 未登录操作
    echo "<script>alert(\"请先登录\");window.location.href = \"?\";</script>";
    exit();
} else if($_REQUEST["_action"] === "signout") {
    // 注销登录操作
    session_destroy();
    header("Location: ?");
} else if($_REQUEST["_action"] === "mkdir") {
    // 新建文件夹操作
    imkdir(get_target(), $_REQUEST["_name"]);
} else if($_REQUEST["_action"] === "rmrf") {
    // 删除文件夹操作
    rmrf(get_target(), $_REQUEST["_name"]);
} else if ($_REQUEST["_action"] === "upload") {
    // 上传文件操作
    upload(get_target(), $_FILES["_file"]);
} else if ($_REQUEST["_action"] === "rename") {
    // 重命名操作
    irename(get_target(), $_REQUEST["_oldname"], $_REQUEST["_newname"]);
} else if ($_REQUEST["_action"] === "download") {
    // 下载文件操作
    download($_REQUEST["_path"]);
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $web_title ?></title>
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <style>
        a {
            text-decoration: none!important;
        }
        table {
            text-wrap: nowrap!important;
        }
        td button {
            margin: 0 5px;
        }
    </style>
    <script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.2/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <span class="navbar-brand"><i class="bi bi-bucket mr-2"></i><?php echo $web_title ?></span>
    <?php
    if($_SESSION["_user_signin"]) {
        ?>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item active">
                <a class="nav-link" href="?_action=signout">退出</a>
            </li>
        </ul>
        <?php
    }
    ?>
</nav>
<div class="container-fluid" style="padding: 30px;">
    <?php
    if($_SESSION["_user_signin"]){
        $target = get_target();
        $lsList = ls($target);
        ?>
        <div class="row">
            <div class="col-12">
                <nav id="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="?_target=.">Home</a></li>
                        <?php
                        $breadcrumb = explode("/", $target);
                        $breadcrumb_path = "";
                        foreach ($breadcrumb as $entry){
                            if ($entry === "" || $entry === ".") {
                                continue;
                            } ?>
                            <li class="breadcrumb-item">
                                <a href="?_target=<?php echo $breadcrumb_path.$entry; ?>"><?php echo $entry; ?></a>
                            </li>
                            <?php
                            $breadcrumb_path .= $entry."/";
                        } ?>
                    </ol>
                </nav>
            </div>
            <div class="col-12 col-lg-4 col-md-5 col-sm-6">
                <form action="" method="post">
                    <input value="mkdir" name="_action" hidden>
                    <div class="form-group">
                        <div class="input-group">
                            <input type="text" class="form-control" name="_name" placeholder="请输入文件夹名称" required>
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-secondary">新建文件夹</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-12 col-lg-4 col-md-5 col-sm-6">
                <form action="" method="post" enctype="multipart/form-data">
                    <input value="upload" name="_action" hidden>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" name="_file" id="file" required>
                                <label class="custom-file-label" for="file" data-browse="选择">请选择要上传的文件</label>
                            </div>
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-secondary">上传</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-12">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                        <tr>
                            <th style="width: 10px; text-align: center;"></th>
                            <th>名称</th>
                            <th>大小</th>
                            <th>修改时间</th>
                            <th>操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        if (count($lsList) > 0) {
                            foreach ($lsList as $entry) { ?>
                                <tr>
                                    <td>
                                        <?php if ($entry["is_dir"]) {?>
                                            <i class="bi bi-folder"></i>
                                        <?php } else { ?>
                                            <i class="bi bi-file-earmark"></i>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo $entry["is_dir"] ? "?_target=" . $entry["path"] : "./".$entry["path"]; ?>"
                                           target="<?php echo $entry["is_dir"] ? "_self" : "_blank"; ?>">
                                            <?php echo $entry["name"] ?>
                                        </a>
                                    </td>
                                    <td data-sort="<?php echo $entry["is_dir"] ? -1 : $entry["size"] ?>">
                                        <?php echo $entry["is_dir"] ? "---" : format_size($entry["size"]); ?>
                                    </td>
                                    <td>
                                        <?php echo format_mtime($entry["mtime"]); ?>
                                    </td>
                                    <td>
                                        <?php
                                        if(!$entry["is_dir"]) {
                                            ?>
                                            <button onclick="window.open('?_action=download&_path=<?php echo $entry["path"]; ?>', '_blank')" type="submit" class="btn btn-link text-primary" style="padding: 0; border: none;">
                                                <i class="bi bi-download mr-2"></i>下载
                                            </button>
                                            <?php
                                        }
                                        ?>
                                        <form action="" method="post" class="d-inline-block">
                                            <input value="rename" name="_action" hidden>
                                            <input value="<?php echo $entry["name"]; ?>" name="_oldname" hidden>
                                            <input value="<?php echo $entry["name"]; ?>" name="_newname" hidden>
                                            <button onclick="let name = prompt('请输入新的名称：', '<?php echo $entry["name"]; ?>'); if(!name) { return false; } $(this).parent().find('input[name=_newname]').val(name)" type="submit" class="btn btn-link text-warning" style="padding: 0; border: none;">
                                                <i class="bi bi-pencil-square mr-2"></i>重命名
                                            </button>
                                        </form>
                                        <form action="" method="post" class="d-inline-block">
                                            <input value="rmrf" name="_action" hidden>
                                            <input value="<?php echo $entry["name"]; ?>" name="_name" hidden>
                                            <button onclick="if(!confirm('确定要该文件/文件夹删除吗')) { return false; }" type="submit" class="btn btn-link text-danger" style="padding: 0; border: none;">
                                                <i class="bi bi-trash mr-2"></i>删除
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            ?>
                            <tr>
                                <td colspan="5">
                                    暂无文件/文件夹
                                </td>
                            </tr>
                            <?php
                        } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <script>
            $("input[type=file]").on("change", function(e){
                let fileName = e.target.files[0].name;
                $(this).next(".custom-file-label").html(fileName);
            })

            $.fn.tableSorter = function() {
                let $table = this;
                this.find('th').click(function() {
                    let idx = $(this).index();
                    let direction = $(this).hasClass("sort_asc");
                    $table.tableSortBy(idx, direction);
                });
                return this;
            };

            $.fn.tableSortBy = function(idx, direction) {
                let $rows = this.find("tbody tr");
                function elementToVal(a) {
                    let $a_elem = $(a).find("td:nth-child(" + (idx + 1) + ")");
                    let a_val = $a_elem.attr("data-sort") || $a_elem.text();
                    return (a_val === parseInt(a_val) ? parseInt(a_val) : a_val);
                }
                $rows.sort(function(a, b){
                    let a_val = elementToVal(a), b_val = elementToVal(b);
                    return (a_val > b_val ? 1 : (a_val === b_val ? 0 : -1)) * (direction ? 1 : -1);
                })
                this.find("th").removeClass("sort_asc sort_desc");
                $(this).find("thead th:nth-child(" + (idx + 1) + ")").addClass(direction ? "sort_desc" : "sort_asc");
                for(let i = 0; i < $rows.length; i++) {
                    this.append($rows[i]);
                }
                this.setTableSortMarkers();
                return this;
            }
            $.fn.reTableSort = function() {
                let $e = this.find("thead th.sort_asc, thead th.sort_desc");
                if($e.length) {
                    this.tablesortby($e.index(), $e.hasClass("sort_desc"));
                }

                return this;
            }
            $.fn.setTableSortMarkers = function() {
                this.find("thead th span.indicator").remove();
                this.find("thead th.sort_asc").append("<span class=\"indicator\">&darr;<span>");
                this.find("thead th.sort_desc").append("<span class=\"indicator\">&uarr;<span>");
                return this;
            }

            $('.table').tableSorter();
        </script>
    <?php
    } else {
    ?>
        <div class="row justify-content-center" style="margin-top: 3rem;">
            <div class="col-12 col-lg-3 col-md-6 col-sm-8 text-center">
                <h3 class="mb-4">请先登录</h3>
                <form action="?" method="post">
                    <input value="signin" name="_action" hidden>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <div class="input-group-text">账户账号</div>
                            </div>
                            <input type="text" class="form-control" name="_userAccount" placeholder="请输入账户账号" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <div class="input-group-text">账户密码</div>
                            </div>
                            <input type="password" class="form-control" name="_userPassword" placeholder="请输入账户密码" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">登录</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    ?>
</body>
</html>
