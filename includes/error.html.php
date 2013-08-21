<?php
//////////
// template which display customer-friendly message in case remote store failed to authenticate with HSPc
//
// $Id: error.html.php 853959 2013-03-13 08:38:14Z dkolvakh $
//////////
?><html>
<head>
<title>404 Not Found</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
</head>
<link href="/shop/css/stylesheet.css" rel="stylesheet" type="text/css" />
<body marginwidth="0" marginheight="0" leftmargin="0" topmargin="0" class="body" bgcolor="#ffffff">
<br /><br />
<table border="0" width="500" align="center">
<tr>
	<td align="center"> 
		<h1>No Web Site Is Configured At This Address</h1>
		<hr>
	</td>
</tr>
<tr>
	<td align="left">
		<ul>
		<li>Please use Fully Qualified Domain Name instead of an IP Address.</li>
		<li>The site could be temporarily unavailable. Try again in a few moments.</li>
		</ul>
	</td>
</tr>
</table>
<?php
	if(get_error_handler()->has(MC_INTERR)) {
?>
		<br>
		<table cellpadding="5" cellspacing="0" border="0" width="100%" class="Internal_Error_bg">
			<tr>
				<td width="100%" class="status_text">
					<?php echo get_error_handler()->get(MC_INTERR);?>
				</td>
			</tr>
		</table>
		<br>
<?php
	}
?>
</body>
</html>