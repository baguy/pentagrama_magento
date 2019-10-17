# pentagrama_magento
Teste de instalação de módulos no Magento 1.9

Feito em PHP: 5.5.15

Frontend
http://127.0.0.1/pentagrama_magento/magento/index.php/

Backend
http://127.0.0.1/pentagrama_magento/magento/admin
Usuário: pentagrama_magento
Senha: pentagrama_magento_senha2019

Roteiro

## Desenvolvimento / Configuração Backend
- Configurar para que vendas não possam ser realizadas por convidados (Allow Guest Checkout)
- Configurar para que seja exibido Tax/Vat Number (CPF)
- Configurar para que sejam obrigatórios os campos de data de nascimento, gênero e Tax/Vat
- Configurar para que sejam exibidas 4 linhas de endereço que correspondem a: Rua, Número, Complemento e Bairro
- Configurar para que o país padrão seja o Brasil e permitir compras apenas para o brasil
- Configurar a Origem do Envio
- Adicionar Estados Brasileiros (http://mariosam.com.br/magento/sql-estados/)
- Cadastrar 3 produtos simples com estoque, pelo menos 1 foto
- Customizar a listagem de pedidos para exibir os itens de pedido no grid e permitir filtragem dos mesmos

## Desenvolvimento / Configuração Frontend
_NÃO é necessário IMPLEMENTAR as mascaras nos campos de CEP, CPF, Telefone e etc;
Quando for realizar o pedido favor formatar os campos pois o gateway do Pagar.me faz validações_
(Ex.: em vez de digitar CPF como 12345678901 digitar como 123.456.789-01, aniversário 20022017 digitar como 20/02/2017)
- Fazer o Pedido utilizando o método de Boleto Bancário do Módulo Pagar.me
- Realizar uma compra com 2 itens e frete para 30110-017
- Criar novo módulo Destaque com link no menu da homepage e gerar nova rota no frontend para exibir um texto(oportunidade única) e um produto especifico
