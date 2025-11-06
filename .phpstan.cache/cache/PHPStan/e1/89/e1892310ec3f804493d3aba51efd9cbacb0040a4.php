<?php declare(strict_types = 1);

// odsl-/Users/jerome/Projects/cerberus-iam/laravel-iam/src
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v1',
   'data' => 
  array (
    '/Users/jerome/Projects/cerberus-iam/laravel-iam/src/Filters/UserDirectoryFilter.php' => 
    array (
      0 => '672948e893cd46e934388578f1d3d6ec350497d8',
      1 => 
      array (
        0 => 'cerberusiam\\filters\\userdirectoryfilter',
      ),
      2 => 
      array (
        0 => 'cerberusiam\\filters\\__construct',
        1 => 'cerberusiam\\filters\\toqueryparameters',
        2 => 'cerberusiam\\filters\\email',
        3 => 'cerberusiam\\filters\\search',
        4 => 'cerberusiam\\filters\\organisation',
        5 => 'cerberusiam\\filters\\role',
        6 => 'cerberusiam\\filters\\team',
        7 => 'cerberusiam\\filters\\mfa',
        8 => 'cerberusiam\\filters\\status',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jerome/Projects/cerberus-iam/laravel-iam/src/Middleware/EnsureCerberusAuthenticated.php' => 
    array (
      0 => 'f382857a7e1ef54cacb84550dc2b8cb61b9d2806',
      1 => 
      array (
        0 => 'cerberusiam\\middleware\\ensurecerberusauthenticated',
      ),
      2 => 
      array (
        0 => 'cerberusiam\\middleware\\__construct',
        1 => 'cerberusiam\\middleware\\handle',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jerome/Projects/cerberus-iam/laravel-iam/src/Auth/CerberusUserProvider.php' => 
    array (
      0 => '4221d01041c865f0c4037c6bb5530208ba0e77d7',
      1 => 
      array (
        0 => 'cerberusiam\\auth\\cerberususerprovider',
      ),
      2 => 
      array (
        0 => 'cerberusiam\\auth\\__construct',
        1 => 'cerberusiam\\auth\\retrievebyid',
        2 => 'cerberusiam\\auth\\retrievebytoken',
        3 => 'cerberusiam\\auth\\updateremembertoken',
        4 => 'cerberusiam\\auth\\retrievebycredentials',
        5 => 'cerberusiam\\auth\\validatecredentials',
        6 => 'cerberusiam\\auth\\rehashpasswordifrequired',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jerome/Projects/cerberus-iam/laravel-iam/src/Auth/CerberusUser.php' => 
    array (
      0 => '17a9ce3c3607a968ea6bf02487ba9e905e75a49f',
      1 => 
      array (
        0 => 'cerberusiam\\auth\\cerberususer',
      ),
      2 => 
      array (
        0 => 'cerberusiam\\auth\\__construct',
        1 => 'cerberusiam\\auth\\fromprofile',
        2 => 'cerberusiam\\auth\\getauthidentifiername',
        3 => 'cerberusiam\\auth\\getauthidentifier',
        4 => 'cerberusiam\\auth\\getauthpassword',
        5 => 'cerberusiam\\auth\\getauthpasswordname',
        6 => 'cerberusiam\\auth\\getremembertoken',
        7 => 'cerberusiam\\auth\\setremembertoken',
        8 => 'cerberusiam\\auth\\getremembertokenname',
        9 => 'cerberusiam\\auth\\getattribute',
        10 => 'cerberusiam\\auth\\toarray',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jerome/Projects/cerberus-iam/laravel-iam/src/Auth/CerberusGuard.php' => 
    array (
      0 => '5ae33bf339fea099231ae8afbdb99cba17bd5845',
      1 => 
      array (
        0 => 'cerberusiam\\auth\\cerberusguard',
      ),
      2 => 
      array (
        0 => 'cerberusiam\\auth\\__construct',
        1 => 'cerberusiam\\auth\\check',
        2 => 'cerberusiam\\auth\\guest',
        3 => 'cerberusiam\\auth\\user',
        4 => 'cerberusiam\\auth\\id',
        5 => 'cerberusiam\\auth\\validate',
        6 => 'cerberusiam\\auth\\setuser',
        7 => 'cerberusiam\\auth\\hasuser',
        8 => 'cerberusiam\\auth\\viaremember',
        9 => 'cerberusiam\\auth\\login',
        10 => 'cerberusiam\\auth\\loginusingid',
        11 => 'cerberusiam\\auth\\once',
        12 => 'cerberusiam\\auth\\onceusingid',
        13 => 'cerberusiam\\auth\\attempt',
        14 => 'cerberusiam\\auth\\attemptwhen',
        15 => 'cerberusiam\\auth\\loginfromauthorizationcode',
        16 => 'cerberusiam\\auth\\logout',
        17 => 'cerberusiam\\auth\\redirecttocerberus',
        18 => 'cerberusiam\\auth\\gettokenstore',
        19 => 'cerberusiam\\auth\\setrequest',
        20 => 'cerberusiam\\auth\\resolveuserfromstoredtokens',
        21 => 'cerberusiam\\auth\\resolveuserfromsessioncookie',
        22 => 'cerberusiam\\auth\\retrieveuserfromaccesstoken',
        23 => 'cerberusiam\\auth\\normalizetokenpayload',
        24 => 'cerberusiam\\auth\\defaultscopes',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jerome/Projects/cerberus-iam/laravel-iam/src/Contracts/TokenStore.php' => 
    array (
      0 => 'a6b8fa2688c9c9c99647ec2127028437026db439',
      1 => 
      array (
        0 => 'cerberusiam\\contracts\\tokenstore',
      ),
      2 => 
      array (
        0 => 'cerberusiam\\contracts\\store',
        1 => 'cerberusiam\\contracts\\retrieve',
        2 => 'cerberusiam\\contracts\\clear',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jerome/Projects/cerberus-iam/laravel-iam/src/Contracts/OAuthStateStore.php' => 
    array (
      0 => '34b71481404244ce1300099c50caebb4ba5ca426',
      1 => 
      array (
        0 => 'cerberusiam\\contracts\\oauthstatestore',
      ),
      2 => 
      array (
        0 => 'cerberusiam\\contracts\\putstate',
        1 => 'cerberusiam\\contracts\\pullstate',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jerome/Projects/cerberus-iam/laravel-iam/src/Contracts/IamClient.php' => 
    array (
      0 => '89866d137fd4f2a21e67666cb6161bcba58cceb2',
      1 => 
      array (
        0 => 'cerberusiam\\contracts\\iamclient',
      ),
      2 => 
      array (
        0 => 'cerberusiam\\contracts\\sessioncookiename',
        1 => 'cerberusiam\\contracts\\buildauthorizationurl',
        2 => 'cerberusiam\\contracts\\generatecodeverifier',
        3 => 'cerberusiam\\contracts\\exchangeauthorizationcode',
        4 => 'cerberusiam\\contracts\\refreshaccesstoken',
        5 => 'cerberusiam\\contracts\\getuserinfo',
        6 => 'cerberusiam\\contracts\\getcurrentuserfromsession',
        7 => 'cerberusiam\\contracts\\logoutsession',
        8 => 'cerberusiam\\contracts\\revoketokens',
        9 => 'cerberusiam\\contracts\\getuserbyid',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jerome/Projects/cerberus-iam/laravel-iam/src/Contracts/UserRepository.php' => 
    array (
      0 => '2273ed5588581f5afcc1a7bc0be363b95214fcc7',
      1 => 
      array (
        0 => 'cerberusiam\\contracts\\userrepository',
      ),
      2 => 
      array (
        0 => 'cerberusiam\\contracts\\list',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jerome/Projects/cerberus-iam/laravel-iam/src/Repositories/UserDirectoryRepository.php' => 
    array (
      0 => '79399ef4a299b375f9440eef55be3613a635795e',
      1 => 
      array (
        0 => 'cerberusiam\\repositories\\userdirectoryrepository',
      ),
      2 => 
      array (
        0 => 'cerberusiam\\repositories\\__construct',
        1 => 'cerberusiam\\repositories\\list',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jerome/Projects/cerberus-iam/laravel-iam/src/Support/Stores/SessionOAuthStateStore.php' => 
    array (
      0 => '015fc35a787297b02bc1e30f999715999bb27fb8',
      1 => 
      array (
        0 => 'cerberusiam\\support\\stores\\sessionoauthstatestore',
      ),
      2 => 
      array (
        0 => 'cerberusiam\\support\\stores\\__construct',
        1 => 'cerberusiam\\support\\stores\\putstate',
        2 => 'cerberusiam\\support\\stores\\pullstate',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jerome/Projects/cerberus-iam/laravel-iam/src/Support/Stores/SessionTokenStore.php' => 
    array (
      0 => '810722d849d96072fb6167a7139bd6bd53cd47e7',
      1 => 
      array (
        0 => 'cerberusiam\\support\\stores\\sessiontokenstore',
      ),
      2 => 
      array (
        0 => 'cerberusiam\\support\\stores\\__construct',
        1 => 'cerberusiam\\support\\stores\\store',
        2 => 'cerberusiam\\support\\stores\\retrieve',
        3 => 'cerberusiam\\support\\stores\\clear',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jerome/Projects/cerberus-iam/laravel-iam/src/Http/Clients/CerberusClient.php' => 
    array (
      0 => '871f4ed86c54e95f0fa350e2033d520d02c14a5d',
      1 => 
      array (
        0 => 'cerberusiam\\http\\clients\\cerberusclient',
      ),
      2 => 
      array (
        0 => 'cerberusiam\\http\\clients\\__construct',
        1 => 'cerberusiam\\http\\clients\\sessioncookiename',
        2 => 'cerberusiam\\http\\clients\\buildauthorizationurl',
        3 => 'cerberusiam\\http\\clients\\generatecodeverifier',
        4 => 'cerberusiam\\http\\clients\\exchangeauthorizationcode',
        5 => 'cerberusiam\\http\\clients\\refreshaccesstoken',
        6 => 'cerberusiam\\http\\clients\\getuserinfo',
        7 => 'cerberusiam\\http\\clients\\getcurrentuserfromsession',
        8 => 'cerberusiam\\http\\clients\\logoutsession',
        9 => 'cerberusiam\\http\\clients\\revoketokens',
        10 => 'cerberusiam\\http\\clients\\getuserbyid',
        11 => 'cerberusiam\\http\\clients\\tokenrequest',
        12 => 'cerberusiam\\http\\clients\\generatecodechallenge',
        13 => 'cerberusiam\\http\\clients\\clientcredentialsheader',
        14 => 'cerberusiam\\http\\clients\\url',
        15 => 'cerberusiam\\http\\clients\\applydefaults',
        16 => 'cerberusiam\\http\\clients\\getclientcredentialsaccesstoken',
        17 => 'cerberusiam\\http\\clients\\normalizetokenpayload',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jerome/Projects/cerberus-iam/laravel-iam/src/Http/Controllers/CerberusCallbackController.php' => 
    array (
      0 => 'bf01066b0df03764f252bc0ba77c738d8fcddf32',
      1 => 
      array (
        0 => 'cerberusiam\\http\\controllers\\cerberuscallbackcontroller',
      ),
      2 => 
      array (
        0 => 'cerberusiam\\http\\controllers\\__invoke',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jerome/Projects/cerberus-iam/laravel-iam/src/Facades/CerberusIam.php' => 
    array (
      0 => '0db7aff93a41b1f2346962ec73e5bb71974314d7',
      1 => 
      array (
        0 => 'cerberusiam\\facades\\cerberusiam',
      ),
      2 => 
      array (
        0 => 'cerberusiam\\facades\\getfacadeaccessor',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jerome/Projects/cerberus-iam/laravel-iam/src/Providers/CerberusIamServiceProvider.php' => 
    array (
      0 => '5a8a2de663d5d6d85c89cbe0d7be86e6d28426bb',
      1 => 
      array (
        0 => 'cerberusiam\\providers\\cerberusiamserviceprovider',
      ),
      2 => 
      array (
        0 => 'cerberusiam\\providers\\register',
        1 => 'cerberusiam\\providers\\boot',
        2 => 'cerberusiam\\providers\\registerauthdriver',
      ),
      3 => 
      array (
      ),
    ),
  ),
));