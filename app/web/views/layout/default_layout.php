<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <link rel="stylesheet" href="<?php echo GLOBAL_RESOURCE_URL.'/bootstrap-3.3.7/css/bootstrap.min.css'; ?>">
    <link rel="stylesheet" href="<?php echo GLOBAL_RESOURCE_URL.'/bootstrap-3.3.7/css/bootstrap-theme.min.css'; ?>">

    <script src="<?php echo GLOBAL_RESOURCE_URL.'/js/jquery-3.2.1.min.js' ?>"></script>
    <script src="<?php echo GLOBAL_RESOURCE_URL.'/bootstrap-3.3.7/js/bootstrap.min.js' ?>"></script>

    <!--[if IE]>
    <script src="http://libs.useso.com/js/html5shiv/3.7/html5shiv.min.js"></script>
    <![endif]-->

    <title><?php echo $tpl_output['html_title']; ?></title>
</head>
<body>
    <?php include $tpl_file;?>
</body>
</html>