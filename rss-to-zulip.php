<?php
// Configurações
$rss_feed_url = "https://SEU.FEED.RSS/rss/noticias"; // Exemplo: "https://www.example.com/rss"
$last_checked_file = "last_checked.txt"; // Arquivo para armazenar a última data de verificação
$processed_items_file = "processed_items.txt"; // Arquivo para armazenar itens já processados
$log_file = "/var/log/rss-to-zulip/script_log.txt"; // Arquivo para logs detalhados
$base_dir = "/var/www/html/img/zulip/"; // Diretório para salvar imagens localmente
$base_url = "https://SEU.DOMINIO.COM/img/zulip/"; // URL base para acessar imagens (servidor web onde estão as imagens>$image_expiration_days = 7; // Quantidade de dias para manter as imagens

// --- Configurações do Zulip ---
$zulip_api_url = "https://SEU.DOMINIO.DO.ZULIP/api/v1/messages"; // Exemplo: "https://your-zulip-instance.com/api/v1/m>$zulip_bot_email = "noticia-bot@chat.castro.pr.gov.br"; // E-mail do seu bot Zulip
$zulip_bot_email = "SEU-bot@SEU.DOMINIO.DO.ZULIP"; // E-mail do seu bot Zulip
$zulip_bot_api_key = "API-KEY-AQUI"; // Chave da API do seu bot Zulip
$zulip_stream_name = "NOME-DO-CANAL"; // Nome do canal (stream) do Zulip onde a notícia será publicada
$zulip_topic_name = "NOME-DO-TOPICO"; // Tópico da mensagem no Zulip
// --- Fim das Configurações do Zulip ---


// Função para registrar logs
function logMessage($message) {
    global $log_file;
    // Garante que o diretório de log exista
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
        // Tentar definir permissões para o novo diretório
        @chmod($log_dir, 0755); 
    }
    $time = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$time] $message" . PHP_EOL, FILE_APPEND);
}

/**
 * Envia uma mensagem para um canal específico no Zulip.
 *
 * @param string $title O título da notícia.
 * @param string $description A descrição da notícia.
 * @param string $link O URL completo da notícia.
 * @param string $imageUrl (Opcional) O URL da imagem a ser exibida.
 * @return bool Retorna true se a mensagem foi enviada com sucesso, false caso contrário.
 */
function sendToZulip($title, $description, $link, $imageUrl = "") {
    global $zulip_api_url, $zulip_bot_email, $zulip_bot_api_key, $zulip_stream_name, $zulip_topic_name;

    // Constrói o conteúdo da mensagem em Markdown para o Zulip
    $message_content = "**{$title}**\n\n";
    $message_content .= "{$description}\n\n";
    $message_content .= "Leia mais: [{$link}]({$link})\n";
    if (!empty($imageUrl)) {
        // Usa a sintaxe de imagem Markdown. Zulip tentará gerar thumbnail.
        $message_content .= "![Imagem]({$imageUrl})\n"; 
    }

    $data = [
        "type" => "stream",
        "to" => $zulip_stream_name,
        "topic" => $zulip_topic_name,
        "content" => $message_content,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $zulip_api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); // Envia dados como application/x-www-form-urlencoded
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $zulip_bot_email . ":" . $zulip_bot_api_key); // Autenticação básica

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        $error = curl_error($ch);
        logMessage("Erro ao enviar para Zulip (CURL): $error");
    }

    curl_close($ch);
    logMessage("Resposta do Zulip (HTTP $http_code): " . $response);

    if ($http_code == 200) {
        logMessage("Notícia publicada no Zulip com sucesso: $title");
        return true;
    } else {
        logMessage("Falha ao publicar notícia no Zulip. HTTP Code: $http_code. Resposta: $response");
        return false;
    }
}

// Função para limpar imagens antigas
function cleanOldImages($directory, $daysToKeep) {
    // Garante que o diretório exista
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
        @chmod($directory, 0755); // Tentar definir permissões
        logMessage("Diretório de imagens criado: $directory");
        return; // Nada para limpar se o diretório acabou de ser criado
    }

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

// Função para baixar e salvar a imagem localmente (com normalização GD)
function saveImageLocally($url, $saveDir) {
    // Garante que o diretório de destino exista
    if (!is_dir($saveDir)) {
        mkdir($saveDir, 0755, true);
        @chmod($saveDir, 0755); // Tentar definir permissões
        logMessage("Diretório de imagens criado: $saveDir");
    }

    // Gera um nome único para o arquivo
    $fileName = uniqid() . ".jpg"; // Sempre tenta salvar como JPG para consistência
    $filePath = $saveDir . $fileName;

    // Tenta fazer o download da imagem
    $imageData = @file_get_contents($url);
    if ($imageData === false) {
        logMessage("Falha ao baixar a imagem: $url. Pode ser uma URL inválida ou problema de rede.");
        return false;
    }

    // Tenta carregar a imagem com GD para normalização e garantir formato JPG
    if (extension_loaded('gd') && function_exists('imagecreatefromstring')) {
        try {
            $image = @imagecreatefromstring($imageData); // Usar @ para suprimir warnings em caso de formato inválido
            if ($image !== false) {
                // Se carregou com sucesso, recria e salva como JPG para garantir compatibilidade
                // Qualidade de 85 é um bom equilíbrio entre tamanho e qualidade para web
                if (imagejpeg($image, $filePath, 85)) {
                    logMessage("Imagem normalizada e salva com GD: $filePath");
                    imagedestroy($image); // Libera memória
                    return $fileName;
                } else {
                    logMessage("Falha ao salvar a imagem normalizada com GD: $filePath. Verifique permissões de escrita ou configurações GD.");
                    imagedestroy($image); // Libera memória
                }
            } else {
                logMessage("Não foi possível carregar a imagem com GD para normalização. Tentando salvar o original. URL: $url");
            }
        } catch (Throwable $e) { // Captura Exception e Error para PHP 7+
            logMessage("Erro durante a normalização da imagem com GD: " . $e->getMessage());
        }
    } else {
        logMessage("Extensão GD não carregada ou imagecreatefromstring não existe. Salvando imagem original sem normalização.");
    }

    // Fallback: Se a normalização falhar, GD não estiver disponível, ou ocorrer um erro, tenta salvar o conteúdo original diretamente
    if (file_put_contents($filePath, $imageData)) {
        logMessage("Imagem salva diretamente (sem normalização GD): $filePath");
        return $fileName;
    } else {
        logMessage("Falha final ao salvar a imagem localmente: $filePath. Verifique permissões de escrita.");
        return false;
    }
}


// --- INÍCIO DA LÓGICA PRINCIPAL ---

// Limpar imagens antigas antes de processar novas
cleanOldImages($base_dir, $image_expiration_days);

// Recuperar a última data de verificação
$last_checked = @file_get_contents($last_checked_file);
if ($last_checked === false) {
    $last_checked = 0; // Primeira execução
    logMessage("Primeira execução do script. Definindo last_checked para 0.");
} else {
    $last_checked = (int) $last_checked; // Converte para inteiro para comparação
    logMessage("Última verificação em: " . date('Y-m-d H:i:s', $last_checked));
}

// Carregar a lista de itens já processados
$processed_items = file_exists($processed_items_file) ? file($processed_items_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$processed_items = array_map('trim', $processed_items); // Garante que não há espaços em branco
logMessage("Itens já processados carregados: " . count($processed_items));

// Obter e processar o feed RSS
logMessage("Carregando feed RSS de: $rss_feed_url");
$rss = @simplexml_load_file($rss_feed_url);
if ($rss === false) {
    logMessage("Erro ao carregar o feed RSS. Verifique a URL e a conectividade. Erro: " . error_get_last()['message']);
    exit("Erro ao carregar o feed RSS. Verifique o log para mais detalhes.");
}

$new_items_found = false;
foreach ($rss->channel->item as $item) {
    $pubDate = strtotime((string)$item->pubDate); // Data de publicação do item
    $link = (string)$item->link; // Identificador único do item (URL da notícia)

    // Verificar se o item é novo baseado na data E se já foi processado (usando o link como identificador único)
    if ($pubDate <= $last_checked || in_array($link, $processed_items)) {
        logMessage("Item ignorado (já processado ou muito antigo): $link");
        continue; // Pula para o próximo item
    }

    $new_items_found = true;
    $title = (string)$item->title;
    $description = (string)$item->description;

    // Extrair URL da imagem de <media:content>, com fallbacks
    $imageUrl = "";
    $mediaContent = $item->children('media', true);
    if (isset($mediaContent->content) && isset($mediaContent->content->attributes()->url)) {
        $imageUrl = (string)$mediaContent->content->attributes()->url;
        logMessage("Imagem encontrada via media:content: $imageUrl");
    } elseif (isset($item->enclosure) && isset($item->enclosure->attributes()->url) && str_starts_with((string)$item->enclosure->attributes()->type, 'image/')) {
        $imageUrl = (string)$item->enclosure->attributes()->url;
        logMessage("Imagem encontrada via enclosure: $imageUrl");
    } elseif (isset($item->image) && !empty((string)$item->image)) {
        $imageUrl = (string)$item->image;
        logMessage("Imagem encontrada via image tag: $imageUrl");
    } else {
        logMessage("Nenhuma URL de imagem encontrada para o item: $title");
    }

    // Salvar imagem localmente, se disponível
    $savedImageName = false;
    $localImageUrl = "";
    if (!empty($imageUrl)) {
        $savedImageName = saveImageLocally($imageUrl, $base_dir);
        if ($savedImageName) {
            $localImageUrl = $base_url . $savedImageName;
            logMessage("URL da imagem local para Zulip: $localImageUrl");
        } else {
            logMessage("Não foi possível salvar a imagem localmente para: $imageUrl. A mensagem para Zulip será enviada sem imagem.");
        }
    }

    logMessage("Preparando envio para Zulip: Título - $title | Link - $link");

    // Enviar requisição para o Zulip
    if (sendToZulip($title, $description, $link, $localImageUrl)) {
        logMessage("Notificação enviada com sucesso para o Zulip: $link");
        // Registrar item como processado APENAS se o envio para o Zulip for bem-sucedido
        file_put_contents($processed_items_file, $link . PHP_EOL, FILE_APPEND);
    } else {
        logMessage("Falha ao enviar notificação para o Zulip: $link. O item não será marcado como processado e será tentado novamente na próxima execução.");
    }
}

// Atualizar a última data de verificação SOMENTE se novos itens foram encontrados e processados,
// ou se é a primeira execução. Isso evita que o script pule itens futuros se houver um erro de RSS contínuo.
if ($new_items_found || $last_checked === 0) {
    file_put_contents($last_checked_file, time());
    logMessage("Data de verificação atualizada para " . date('Y-m-d H:i:s', time()) . ".");
} else {
    logMessage("Nenhum novo item encontrado ou processado. A data de verificação não foi atualizada.");
}


$exectime = date('Y-m-d H:i:s');
echo "Script executado em: " . $exectime . " Consulte o log para detalhes em " . $log_file . PHP_EOL;

?>