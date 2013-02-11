<?php
/* 
 * script feito para o debug via xdebug (netbeans) rode a partir da raiz, não aterando o cwd e mantendo a estrutura padrão de chamada
 */

$crawler = './core/php/crawler.php';
$suite_path = './suites';
$suite_file = $suite_path .'/'. $argv[1] .'.json';
$argv[1] = $suite_file;

require $crawler;
?>
