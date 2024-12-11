<?php
// Configurações
$rss_feed_url = "HTTPS://FEED-URL";
$api_url = "HTTPS://API-URL";
$token = "API-TOKEN"; // Substitua pelo token fornecido
$last_checked_file = "last_checked.txt"; // Arquivo para armazenar a última data de verificação - Usar caminho se for alocar o arquivo em pasta diferente
$processed_items_file = "processed_items.txt"; // Arquivo para armazenar itens já processados - Usar caminho se for alocar o arquivo em pasta diferente
$log_file = "/var/log/rss-to-api/script_log.txt"; // Arquivo para logs detalhados - Usar outro caminho se for alocar o arquivo em pasta diferente
$base_dir = "/var/www/html/img/rss/"; // Diretório para salvar imagens localmente - Usar outro caminho se for alocar o arquivo em pasta diferente
$base_url = "HTTPS://URL-BASE-DAS-IMAGENS"; // URL base para acessar imagens - Url do servidor web onde estão as imagens localmente conforme pasta de $base_dir
$image_expiration_days = 7; // Quantidade de dias para manter as imagens

// Função para registrar logs
function logMessage($message) {
    global $log_file;
    $time = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$time] $message" . PHP_EOL, FILE_APPEND);
}

// Função para enviar requisição POST
function sendPostRequest($url, $data) {
    logMessage("Enviando POST para $url com os dados: " . json_encode($data));
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        $error = curl_error($ch);
        logMessage("Erro no CURL: $error");
    }

    curl_close($ch);
    logMessage("Resposta da API (HTTP $http_code): $response");
    return [$response, $http_code];
}

// Função para limpar imagens antigas
function cleanOldImages($directory, $daysToKeep) {
    $files = glob($directory . "*.jpg"); // Lista arquivos .jpg no diretório
    $now = time();

    foreach ($files as $file) {
        if (is_file($file)) {
            $fileTime = filemtime($file); // Obtém a data de modificação do arquivo
            $age = ($now - $fileTime) / (60 * 60 * 24); // Calcula a idade do arquivo em dias

            if ($age > $daysToKeep) {
                unlink($file); // Remove o arquivo se for mais antigo que o limite
                logMessage("Imagem antiga removida: $file");
            }
        }
    }
}

// Função para baixar e salvar a imagem localmente
function saveImageLocally($url, $saveDir) {
    // Gera um nome único para o arquivo
    $fileName = uniqid() . ".jpg";
    $filePath = $saveDir . $fileName;

    // Tenta fazer o download da imagem
    $imageData = @file_get_contents($url);
    if ($imageData === false) {
        logMessage("Falha ao baixar a imagem: $url");
        return false; // Retorna falso em caso de falha
    }

    // Salva a imagem no caminho especificado
    if (file_put_contents($filePath, $imageData)) {
        logMessage("Imagem salva com sucesso: $filePath");
        return $fileName; // Retorna o nome do arquivo salvo
    } else {
        logMessage("Falha ao salvar a imagem localmente: $filePath");
        return false; // Retorna falso em caso de falha
    }
}

// Limpar imagens antigas antes de processar novas
cleanOldImages($base_dir, $image_expiration_days);

// Recuperar a última data de verificação
$last_checked = @file_get_contents($last_checked_file);
if ($last_checked === false) {
    $last_checked = 0; // Primeira execução
    logMessage("Primeira execução do script.");
} else {
    logMessage("Última verificação em: " . date('Y-m-d H:i:s', $last_checked));
}

// Carregar a lista de itens já processados
$processed_items = file_exists($processed_items_file) ? file($processed_items_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
logMessage("Itens já processados carregados: " . count($processed_items));

// Obter e processar o feed RSS
logMessage("Carregando feed RSS de: $rss_feed_url");
$rss = @simplexml_load_file($rss_feed_url);
if ($rss === false) {
    logMessage("Erro ao carregar o feed RSS. Verifique a URL.");
    exit("Erro ao carregar o feed RSS. Verifique o log para mais detalhes.");
}

foreach ($rss->channel->item as $item) {
    $pubDate = strtotime((string)$item->pubDate); // Data de publicação do item
    $link = (string)$item->link; // Identificador único do item

    // Verificar se o item é novo baseado na data e se já foi processado
    if ($pubDate <= $last_checked || in_array($link, $processed_items)) {
        logMessage("Item ignorado (já processado ou muito antigo): $link");
        continue;
    }

    $title = (string)$item->title;
    $description = (string)$item->description;

    // Extrair URL da imagem de <media:content>
    $mediaContent = $item->children('media', true);
    $image = isset($mediaContent->content) ? (string)$mediaContent->content->attributes()->url : ""; 

    // Salvar imagem localmente, se disponível
    $savedImageName = $image ? saveImageLocally($image, $base_dir) : false;
    $localImageUrl = $savedImageName ? $base_url . $savedImageName : "";

    // Dados da API - Alterar conforme a API
    $data = [
        "token" => $token,
        "mensagem" => $title,
        "link_url" => $link,
        "link_titulo" => $title,
        "link_descricao" => $description,
        "link_imagem" => $localImageUrl,
        "tamanho" => "LARGE"
    ];

    // Log para garantir que o link está sendo enviado corretamente
    logMessage("Preparando envio: Título - $title | Link - $link");

    // Enviar requisição POST para a API
    list($response, $http_code) = sendPostRequest($api_url, $data);

    // Verificar resposta da API
    if ($http_code == 200) {
        logMessage("Notificação enviada com sucesso para: $link");
        file_put_contents($processed_items_file, $link . PHP_EOL, FILE_APPEND); // Registrar item como processado
    } else {
        logMessage("Falha ao enviar notificação. HTTP Code: $http_code. Resposta: $response");
    }
}

// Atualizar a última data de verificação
file_put_contents($last_checked_file, time());
logMessage("Data de verificação atualizada.");
$exectime = date('Y-m-d H:i:s');
echo "Script executado em: " . $exectime . " Consulte o log para detalhes em /var/log/rss-to-api/script_log.txt" . PHP_EOL;
?>
