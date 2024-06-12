# JLPDF
Classe de extensão do TCPDF para facilitar a confecção de relatórios em PHP, especialmente para MadBuilder e Adianti Framework

# Requisitos
tecnickcom/tcpdf
chillerlan/php-qrcode
picqer/php-barcode-generator

# Instalação

1- Crie uma tabela em seu banco de dados executando "sql/schema.sql"
2- Crie um service JLPDF e cole o conteúdo de "app/service/JLPDF.php"
3- Aplique composer do pacote "tecnickcom/tcpdf" em seu projeto

# Uso

Crie um template (como o do exemplo "exemplos/template/exemplo.template"), no nosso caso aplicamos a ele os seguintes parâmetros:

		{
			"key_name": "pag_a4",
			"size": "A4",
			"orientacao": "P",
			"font_size_default": 10,
			"font_family_default": "helvetica"
		}

Crie um codigo_eval seguindo a lógica de obter dados e tratar o template (veja o exemplo "exemplos/codigo_eval/ex1.codEval")

Estando com o template, codigo_eval e parâmetros prontos, basta invocar o relatório seguindo a seguinte lógica:

        $pdf = new JLPDF();
        $pdf->obj_param = $obj_param;
        $jlpdf = $pdf->generatePDF('pag_a4', []);

# Sugestão

Crie um CRUD da tabela em questão para você configurar seu relatório no próprio sistema de forma mais simples ainda, e apenas invoque no código passando um objeto de parâmetros e chamando-o pelo key_name
