#!/usr/bin/php -q
<?php
if ( $argc <= 1 ) {
    echo "Usage: $argv[0] suite [n_instances\n\n";
    exit;
}
//todo: check dependencies
//
//todo: pastas devem ser absolutas. usar scripts para transformar caminhos abaixo com localização absoluta (rodar em qq pasta)

//todo: criar especie de agent/daemon. Configurar então no json como deve rodar a aplicação: repetição, concorrencia, +stack...

//todo: criação do log deveria ser dentro do crawler para que pusessemos o numero do pid no log, assim identificar qual processo se refere o log

//config options
$php = '/usr/bin/php';
$crawler = './core/php/crawler.php';
$output_path = './log';
$suite_path = './suites';

//arguments receive
$suite = $argv[1];
//todo: validate if file exists 
$suite_file = $suite_path .'/'. $suite .'.json';

//number of instances to run simultaneosly
$instances = ( $argc > 2 ) ? $argv[2] : 1;

//check running php instances limit
//todo: deve checar se a instancia se refere ao craw (suite) atual no grep
preg_match_all( '/'.addcslashes($crawler, "/ ").'/', shell_exec('\ps ax -o pid,state,command'), $running );
$running = count($running[0]);
$run = $instances - $running;

//exit;

//php instances caller
$pids = array();
for ( $i = 1; $i <= $run; $i++ ) {
    //log file
    $output_file = "$output_path/$suite-output-". date('ymdHis') .'-'. ($i+$running);
    //php background caller
    $cmd = "nohup \\$php $crawler $suite_file >> $output_file 2>> $output_file &";
    
    $pid = shell_exec("$cmd");
    
    echo "\n", "Started a new crawler instance ($pid). Output at $output_file";
    
    $pids[] = $pid;
    $running++;
}

echo "\n", "Finished. There's $running running instance(s)", "\n\n";

return count($pids);
