<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
<title>ERROR 500</title>
</head>
<body>

<h1>Error</h1>

<hr />

<?php
print_r(debug_print_backtrace());
?>

<pre>

<?= htmlspecialchars($sMessage ?? '') ?>
</pre>

</body>
</html>
