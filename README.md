## Aplicação de envio de publicações de feed RSS para API

Bem-vindo ao repositório do script em PHP para envio de publicações de um rss para uma API.
Este script de baseou na documentação da API do sistema PrefeituraZAP da empresa Lobus Software.

## Índice

- [Requisitos de Software](#requisitos-de-software)
- [Instalação](#instalação)
- [Configuração](#configuração)
- [Uso](#uso)
- [Contribuição](#contribuição)

## Requisitos de Software

Para executar esta aplicação, é necessário ter os seguintes softwares instalados:

- Sistema Linux
- PHP 8.2+
- Servidor WEB (domínio público acessivel neste servidor)
- Cron

## Instalação

Siga os passos abaixo para instalar a aplicação:

1. Clone o repositório para o seu ambiente local:
    ```bash
    git clone https://github.com/adrianolerner/rss-to-api.git
    ```

2. Navegue até o diretório do projeto:
    ```bash
    cd rss-to-api
    ```

3. Certifique-se de ter os requisitos de software instalados e configurados.

## Configuração

Para configurar a aplicação, siga os passos abaixo:

1. **Ajuste a configuração do Script:**
    - Abra o arquivo usando um editor de texto, como por exemplo:
		```bash
		sudo nano rss-to-api.php
		```

2. **Edite os campos abaixo no arquivo conforme sua necessidade e informações:**
    - $rss_feed_url = "HTTPS://FEED-URL";
	- $api_url = "HTTPS://API-URL";
	- $token = "API-TOKEN"; // Substitua pelo token fornecido
	- $last_checked_file = "last_checked.txt"; // Arquivo para armazenar a última data de verificação - Usar caminho se for alocar o arquivo em pasta diferente
	- $processed_items_file = "processed_items.txt"; // Arquivo para armazenar itens já processados - Usar caminho se for alocar o arquivo em pasta diferente
	- $log_file = "/var/log/rss-to-api/script_log.txt"; // Arquivo para logs detalhados - Usar outro caminho se for alocar o arquivo em pasta diferente
	- $base_dir = "/var/www/html/img/rss/"; // Diretório para salvar imagens localmente - Usar outro caminho se for alocar o arquivo em pasta diferente
	- $base_url = "HTTPS://URL-BASE-DAS-IMAGENS"; // URL base para acessar imagens - Url do servidor web onde estão as imagens localmente conforme pasta de $base_dir
	- $image_expiration_days = 7; // Quantidade de dias para manter as imagens

3. **Agende a execução do script conforme o exemplo abaixo na cron:**
    - Abra o editor da cron:
        ```bash
        sudo crontab -e
        ```
    - Exemplo de agendamento - Ajuste conforme sua necessidade:
		```bash
        0 * * * * php /root/rss-to-api.php >> /var/log/rss-to-api/php-rss_log.txt 2>&1
        ```
## Uso

Após a configuração e agendamento, o script irá executar no horário agendado, coletando os dados do feed RSS e fazendo a request para a API JSON.
Pode ser necessários ajustes nos campos e request dependendo da API usada e das peculiaridades.
Caso queira testar momentaneamente basta rodar manualment o script conforme exemplo abaixo:

```bash
php /root/rss-to-api.php
```
Por gentileza mantenha os créditos do criador.

## Contribuição

Se você deseja contribuir com este projeto, siga as diretrizes abaixo:

1. Fork este repositório.
2. Crie uma branch para a sua feature ou correção de bug (`git checkout -b minha-feature`).
3. Commit suas alterações (`git commit -am 'Adicionar nova feature'`).
4. Push para a branch (`git push origin minha-feature`).
5. Crie um novo Pull Request.

## Referências Usadas

- [API PrefeituraZAP](https://www.prefeiturazap.com.br/)
