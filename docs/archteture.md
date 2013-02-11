shell/cron
    chama php com config
    determina horarios/frequencia (cron)
php
    recebe config que define opções abaixo
        abre json e faz parse
    .decide query
    .controla locks
    chama phantom
    salva resultado
php tools
    alguma visualização dos dados
    cron jobs
phantom
    pjscrape captura query
nodejs
    portar php para node
    publicar codigo

