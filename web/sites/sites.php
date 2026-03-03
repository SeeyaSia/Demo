<?php

// AlchemizeDev dynamic routing
// Map DDEV hostnames to 'default' site directory
// Matches: alch-demo-* (workspaces and ticket branches)
$alch_host = $_SERVER['HTTP_HOST'] ?? '';
if (preg_match('/^alch-demo-[a-z0-9-]+\.ddev\.site$/', $alch_host)) {
  $sites[$alch_host] = 'default';
}
