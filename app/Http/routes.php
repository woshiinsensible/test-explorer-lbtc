<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$app->get('/', function () use ($app) {
    return $app->version();
});

$app->group(['namespace' => 'App\Http\Controllers\Admin'], function() use ($app)
{
    $app->get('test', 'TestController@Test');
    $app->get('testcmd', 'TestController@TestCmd');
});

$app->group(['namespace' => 'App\Http\Controllers\Block'], function() use ($app)
{
    //mian
    $app->get('listwitnesses', 'BlockController@listwitnesses');

    $app->get('getvotersbywitness', 'BlockController@getvotersbywitness');

    $app->get('getvotebyaddress', 'BlockController@getvotebyaddress');

    $app->get('getwitnessshare', 'BlockController@getwitnessshare');

    $app->get('setredis', 'BlockController@SetRedis');

    $app->get('getactive', 'BlockController@GetActive');

    $app->get('getstandby', 'BlockController@GetStandby');

    $app->get('getlistdelegates', 'BlockController@Getlistdelegates');

    $app->get('search3', 'BlockController@Search3');

    $app->get('getaddressbalance', 'BlockController@GetAddressBalance');

    $app->get('getblockinfo', 'BlockController@GetBlockInfo');

    $app->get('gettxinfo', 'BlockController@GetTxInfo');

    $app->get('index3', 'BlockController@Index3');

    $app->get('setindex', 'BlockController@SetIndex');

    $app->get('getblockbyhash', 'BlockController@GetBlockInfoByHash');

    //setnodestatus
    $app->get('setnodestatus','BlockController@SetNodeStatus');

    //GetNodeStatus
    $app->get('getnodestatus','BlockController@GetNodeStatus');

    //GetTxByAddr
    $app->get('gettxbyaddr','BlockController@GetTxByAddr');

    //SetBlockCount
    $app->get('setblockcount','BlockController@SetBlockCount');

    //gettest
    $app->get('gettest','BlockController@gettest');

    //Test
    $app->get('test','BlockController@Test');

    //Test1
    $app->get('testpan','BlockController@Test1');

    //Test2
    $app->get('testget','BlockController@Test2');

    //Test3
    $app->get('testreg','BlockController@Test3');

    //Test4
    $app->get('testlimit','BlockController@Test4');


    //LbtcRichList
    $app->get('lbtcrichlist','BlockController@LbtcRichList');

    //GetLbtcRichList
    $app->get('getlbtcrichlist','BlockController@GetLbtcRichList');

    //LbtcRichList
    $app->get('lbtcrichpre','BlockController@LbtcRichPre');

    //GetLbtcRichList
    $app->get('getlbtcrichpre','BlockController@GetLbtcRichPre');

    //ListCommittees
    $app->get('listcommittees','BlockController@ListCommittees');

    //GetListCommittees
    $app->get('getlistcommittees','BlockController@GetListCommittees');

    //GetListCommitteeVotes
    $app->get('getlistcommitteevotes','BlockController@GetListCommitteeVotes');

    //GetListVotedCommittee
    $app->get('getlistvotedcommittee','BlockController@GetListVotedCommittee');

    //SetBillsInfo
    $app->get('setbillsinfo','BlockController@SetBillsInfo');

    //GetBillsInfo
    $app->get('getbillsinfo','BlockController@GetBillsInfo');

    //VoterBillsByAddr
    $app->get('voterbillsbyaddr','BlockController@VoterBillsByAddr');

    //GetListCommitteeBills
    $app->get('getlistcommitteebills','BlockController@GetListCommitteeBills');


    /**
     * mobile
     */
    $app->get('mgetlistdelegates', 'BlockController@MGetlistdelegates');


    $app->get('mgettxinfo', 'BlockController@MGetTxInfo');

    //MGetBalance
    $app->get('mgetbalance','BlockController@MGetBalance');

    //MGetTxByAddr
    $app->get('mgettxbyaddr','BlockController@MGetTxByAddr');

    //MGetVoteByAddress
    $app->get('mgetvotebyaddress','BlockController@MGetVoteByAddress');

    //MGetVotersByWitness
    $app->get('mgetblockcount','BlockController@MGetBlockCount');

    //MGetBlockCount
    $app->get('mgetvotersbywitness','BlockController@MGetVotersByWitness');

    //MGetVersion
    $app->get('mgetversion','BlockController@MGetVersion');

    //MSetVersion
    $app->get('msetversion','BlockController@MSetVersion');

    //ListUnSpent
    $app->get('mlistunspent','BlockController@MListUnSpent');

    //MSendRawTransaction
    $app->get('msendrawtransaction','BlockController@MSendRawTransaction');

    //TestApi
    $app->get('mgetnews','BlockController@MGetNews');

    //GenerateToken
    $app->get('generatetoken','BlockController@GenerateToken');

    //getnews
    $app->get('mgetnews2','BlockController@MGetNews2');

    /**
     * mobile end
     */

    //UpdateBillStatus
    $app->get('updatebillstatus','BlockController@UpdateBillStatus');

    //BillOptionsVotes
    $app->get('billoptionsvotes','BlockController@BillOptionsVotes');


    //GetTokenInfo
    $app->get('gettokeninfo','BlockController@GetTokenInfo');

    //GetTokenBalance
    $app->get('gettokenbalance','BlockController@GetTokenBalance');

    //SetOwenToToken
    $app->get('setowentotoken','BlockController@SetOwenToToken');

    //SetTokenToOwen
    $app->get('settokentoowen','BlockController@SetTokenToOwen');

    //GetTokenOrOwner
    $app->get('gettokenorowner','BlockController@GetTokenOrOwner');

    //getaddressname
    $app->get('getaddressname','BlockController@GetAddressName');

    //getnameaddress
    $app->get('getnameaddress','BlockController@GetNameAddress');

    //getaddresstokentxids
    $app->get('getaddresstokentxids','BlockController@GetAddressTokenTxids');



    //addwords
    $app->get('addwords','BlockController@AddWords');

    //TestApi
    $app->get('testapi','BlockController@TestApi');
});
