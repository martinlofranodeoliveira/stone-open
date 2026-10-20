<?php
declare(strict_types=1);
require __DIR__.'/../src/App/Support/ReleaseSettings.php';
require __DIR__.'/../src/App/Infrastructure/Stone/StoneApiException.php';
use App\Support\ReleaseSettings;
use App\Infrastructure\Stone\StoneApiException;
$checks = 0;
function check(bool $ok): void { global $checks; $checks++; if (!$ok) throw new RuntimeException('Offline check failed'); }
$valid = ['DB_NAME'=>'synthetic_database','DB_USER'=>'synthetic_user','DB_PASS'=>str_repeat('x',24)];
$config=ReleaseSettings::database($valid);
check($config['dbname']===$valid['DB_NAME']);
check($config['user']===$valid['DB_USER']);
check($config['pass']===$valid['DB_PASS']);
check($config['host']==='127.0.0.1' && $config['port']===3306);
foreach (['DB_NAME','DB_USER','DB_PASS'] as $key) {
 foreach ([null,'',' ','replace_with_your_value','<CONFIGURE>'] as $bad) {
  try { ReleaseSettings::database(array_replace($valid,[$key=>$bad])); $failed=false; }
  catch (RuntimeException $error) { $failed=true; check(!str_contains($error->getMessage(), (string)$valid['DB_PASS'])); }
  check($failed);
 }
}
foreach ([0,65536,'invalid','3.3',true] as $port) {
 try { ReleaseSettings::database($valid+['DB_PORT'=>$port]); $failed=false; }
 catch (RuntimeException $error) { $failed=true; }
 check($failed);
}
check(ReleaseSettings::database($valid+['DB_PORT'=>'3307'])['port']===3307);
$exception = new StoneApiException(400,'UNIT_CASE','Synthetic provider error',['field'=>'synthetic'],['id'=>'unit-fixture']);
check($exception->getStatus()===400);
check($exception->getErrorCode()==='UNIT_CASE');
check($exception->getErrorMessage()==='Synthetic provider error');
check($exception->getErrors()===['field'=>'synthetic']);
check($exception->getRaw()===['id'=>'unit-fixture']);
echo json_encode(['checks'=>$checks,'failed'=>[]],JSON_THROW_ON_ERROR).PHP_EOL;
