<?php

$code = getparam('create_code');
if(!$code)
{
	controller()->redirect('/renting');
	return;
}

$captcha = new CCaptchaAction(controller(), 'captcha');
$b = $captcha->validate($code, false);
if(!$b)
{
	controller()->redirect('/renting');
	return;
}

// get a new btc address
$btc = getdbosql('db_coins', "symbol='BTC'");
if(!$btc) return;

$remote = new Bitcoin($btc->rpcuser, $btc->rpcpasswd, $btc->rpchost, $btc->rpcport);
$renter = new db_renters;

$renter->created = time();
$renter->updated = time();
$renter->balance = 0;
$renter->unconfirmed = 0;
$renter->save();

$renter = getdbo('db_renters', $renter->id);
$renter->address = $remote->getaccountaddress(yaamp_renter_account($renter));

$renter->apikey = hash("sha256", $renter->address.time().rand());
$renter->save();

$received1 = $remote->getbalance(yaamp_renter_account($renter), 1);
if($received1>0)
{
	$moved = $remote->move(yaamp_renter_account($renter), '', $received1);
	debuglog("create new renter, moving initial $received1");
}

$recents = isset($_COOKIE['deposits'])? unserialize($_COOKIE['deposits']): array();
$recents[$renter->address] = $renter->address;
setcookie('deposits', serialize($recents), time()+60*60*24*30, '/');

user()->setState('yaamp-deposit', $renter->address);
controller()->redirect('settings');



