<!doctype html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
		<title>Wombat Demos</title>
		<?php $color = '72,67,170'; ?>
		
		<style>
			*, *:before, *:after {
			-moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box;
			}
			body {
				font:16px/1.3 Tahoma,Arial,sans-serif;
				margin:0;
			}			
			h1 { padding:0 1rem; }
			h1 a { text-decoration:none; color:rgb(<?= $color; ?>); font-weight:400; }
			h2 { margin:0; font-weight:400; padding:.25rem 0 0; font-size:1.2rem; }
			h3 { font-weight:400; margin:0; font-size:12px; font-style:italic; color:rgba(0,0,0,.3); }
			pre {
				font-size:12px;
				border:1px dashed rgba(0,0,0,.1);
				border-left:0; border-right:0;
				padding:10px;
				background:rgba(255,255,255,.6);
			}
			.block {
				background:rgba(<?= $color; ?>,.1);
				padding:.5rem 1rem;
				margin:0 0 1rem;
			}
			.riglog { line-height:1.5; }
			.riglog.info { color:rgb(<?= $color; ?>); }
			.riglog.warning { color:#d4c229; }
			.riglog.error { color:#d42929; }
			.riglog .stamp {
				color:rgba(0,0,0,.5);
				font-size:.7em;
				font-style:normal;
			}
			.riglog .stamp span {
				padding:0 2px;
				border:1px dashed rgba(0,0,0,.15);
				position:relative; top:-1px;
			}
			pre {
				max-height:300px;
				overflow:auto;
			}
			.comparepreview {
				overflow:hidden;
			}
			.comparepreview pre {margin-top:0;clear:right;}
			.comparepreview > div {
				width:49%;
				float:left;
			}
			.comparepreview h3 {float:right;}
			.comparepreview > div:first-child {margin-right:1%;}
			.comparepreview > div:last-child {margin-left:1%;}
			dl dt {
				font-size:1.5rem;color:rgb(<?= $color; ?>);
				margin-bottom:10px;
			}
			dl dd {
				margin-bottom:30px;
			}
			dl dd textarea {
				display:block;
				max-width:100%;
				width:100%;
				height:120px;
				max-height:500px;
				resize:vertical;
				background:#34352E;
				padding:10px;
				border:0;
				color:#F9F9F5;
			}
			dl dd h4 {margin:0;font-weight:400;}
			dl dd > p {font-size:11px;margin:0;}
			dl dd > p:before { content:"\00bb"; padding-right:5px; }
			fieldset {margin:0;border:3px solid #C7C6E5;max-width:100%;}
			fieldset p {margin-top:0;}
			.two_columns > fieldset {
				width:49%;
				max-width:49%;
				float:left;
				margin-right:1%;
			}
			.two_columns > fieldset,
			.one_column > fieldset {
				background:#fff;
				margin-top:10px;
				padding:15px;
			}
			fieldset legend {
				background:#C7C6E5;
				padding:5px 12px;
				font-size:14px;
				color:rgba(0,0,0,.75);
			}
			.two_columns > fieldset:last-child {margin-right:0;margin-left:1%;}
			.two_columns { overflow:hidden; }
			.response_status {
				font-size:14px;
				font-style:italic;
				color:rgba(0,0,0,.6);
				margin-left:1.3em;
				position:relative;
				background:rgba(255,255,255,.6);
				padding:0 6px 1px 4px;
				border-radius:3px;
			}
			.response_success { color:#0c0; }
			.response_error { color:#c00; }
			button {
				border:3px solid rgb(<?= $color; ?>);
				background:#fff;
				color:rgb(<?= $color; ?>);
				font-weight:bold;
				padding:3px 15px;
				margin:15px 0 0;
				font-size:1rem;
				text-transform:uppercase;
			}
			button[type=button] {
				border:3px solid rgba(0,0,0,.5);
				color:rgba(0,0,0,.5);
			}
			button.small {
				border-width:2px;
				font-size:.8rem;
				padding:2px 6px;
				opacity:.5;
				transition:opacity 200ms ease-in-out;
			}
			button.small:hover {opacity:1;}
			@-webkit-keyframes rotate-forever {
			  0% {
			    -webkit-transform: rotate(0deg);
			    -moz-transform: rotate(0deg);
			    -ms-transform: rotate(0deg);
			    -o-transform: rotate(0deg);
			    transform: rotate(0deg);
			  }
			
			  100% {
			    -webkit-transform: rotate(360deg);
			    -moz-transform: rotate(360deg);
			    -ms-transform: rotate(360deg);
			    -o-transform: rotate(360deg);
			    transform: rotate(360deg);
			  }
			}
			@-moz-keyframes rotate-forever {
			  0% {
			    -webkit-transform: rotate(0deg);
			    -moz-transform: rotate(0deg);
			    -ms-transform: rotate(0deg);
			    -o-transform: rotate(0deg);
			    transform: rotate(0deg);
			  }
			
			  100% {
			    -webkit-transform: rotate(360deg);
			    -moz-transform: rotate(360deg);
			    -ms-transform: rotate(360deg);
			    -o-transform: rotate(360deg);
			    transform: rotate(360deg);
			  }
			}
			@keyframes rotate-forever {
			  0% {
			    -webkit-transform: rotate(0deg);
			    -moz-transform: rotate(0deg);
			    -ms-transform: rotate(0deg);
			    -o-transform: rotate(0deg);
			    transform: rotate(0deg);
			  }
			
			  100% {
			    -webkit-transform: rotate(360deg);
			    -moz-transform: rotate(360deg);
			    -ms-transform: rotate(360deg);
			    -o-transform: rotate(360deg);
			    transform: rotate(360deg);
			  }
			}
			.loading-spinner {
			  -webkit-animation-duration: 0.75s;
			  -moz-animation-duration: 0.75s;
			  animation-duration: 0.75s;
			  -webkit-animation-iteration-count: infinite;
			  -moz-animation-iteration-count: infinite;
			  animation-iteration-count: infinite;
			  -webkit-animation-name: rotate-forever;
			  -moz-animation-name: rotate-forever;
			  animation-name: rotate-forever;
			  -webkit-animation-timing-function: linear;
			  -moz-animation-timing-function: linear;
			  animation-timing-function: linear;
			  width: 0px;
			  height: 0px;
			  border: 6px solid rgb(<?= $color; ?>);
			  border-right-color: transparent;
			  border-radius: 50%;
			  display: inline-block;
			  margin-left:10px;
			}
			.right {
				float:right;
			}
			.api_connection_info {
				overflow:hidden;
				padding-bottom:15px;
			}
			.api_connection_info label {width:33.3333%;display:block;float:left;padding:0 5px;}
			.api_connection_info input {
				padding:6px 4px;
				background:#34352E;
				border:0;
				color:#F9F9F5;
				width:100%;
			}
			.api_connection_info + p button {margin-right:20px;}
			.api_connection_info span {display:block;font-size:11px;color:rgba(0,0,0,.5);}
		</style>
		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
	</head>
<body>
	<main>