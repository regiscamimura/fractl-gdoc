<?php require('DocParser.php'); ?>

<!doctype html>
<html lang="en">

<head>
	<title>Fractl Document Publisher</title>
	<meta name="Description" content="">
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">

</head>

<body>
	<div class="container py-5">
		<h1>Fractl Document Publisher</h1>
		<p>Convert Google Doc to PHP/HTML file</p>

		<form method="post" action="" target="iLoader">
			<div class="form-group">
				<label for="document-url">Document's URL</label>
				<input type="text" class="form-control" id="document-url" name="document_url">
			</div>
			<div class="form-group">
				<label for="file-name">Output File Name</label>
				<input type="text" class="form-control" id="file-namel" name="name">
			</div>
			<button type="submit" name="process" class="btn btn-primary">Submit</button>
			<button type="submit" name="preview" class="btn btn-secondary">Preview</button>
		</form>
		<iframe name="iLoader" style="width: 100%; height: calc(100vh - 300px)" class="mt-3 border-primary rounded border-rounded"></iframe>
	</div>
</body>
</html>