<?php

use chillerlan\QRCode\{QRCode, QROptions};
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\{QRGdImagePNG, QRCodeOutputException};

function validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

class JLPDF extends TCPDF
{
    private static $template = '';
    private static $jl_loops_static = [];
    private static $html_result = '';
    private static $pdf = '';
    private static $jlHeader = '';
    private static $jlFooter = '';
    
    /* NÃO PODE TER __CONSTRUCT, POIS CAUSA CONFLITO COM O __CONSTRUCT DO TCPDF */
    
    /* INICIO - Metodos JLPDF */
    
    public function generatePDF($jlpdf_nm, $data = [], $retornaHtmlApenas = false)
    {
        try {
             
            /* Cria uma cópia fiel do $this em self::$pdf, permitindo a manipulação por métodos não estáticos
            e garantido a integridade */
            self::sincronizarPdf($this);
            
            self::$jl_loops_static = self::$pdf->jl_loops ?? '';
            TTransaction::open(MAIN_DATABASE);
            
                $jlpdf_bd = JlpdfBd::where('key_name', '=', $jlpdf_nm)->first();
        
                if ($jlpdf_bd) {
                    
                    // Tratar caso flag template_file esteja ativa
                    self::template_file($jlpdf_bd);
                    self::eval_file($jlpdf_bd);
                    
                    $this->setProtection(array('copy', 'modify'), '', null, 0, null);
                    
                    $jlpdf_bd->orientacao = !empty($jlpdf_bd->orientacao) ? $jlpdf_bd->orientacao : 'P';
                    $this->setPageOrientation($jlpdf_bd->orientacao);
                    $this->jlpdf_bd = $jlpdf_bd;
                    
                    $html = self::processTemplate($jlpdf_bd->template, $jlpdf_bd->codigo_eval, $data ?? [], $this);
                    
                    // de($html);
                    
                    // $this->SetCreator(PDF_CREATOR);
                    // $this->SetAuthor('Author Name');
                    // $this->SetTitle('Sample Report');
                    // $this->SetSubject('Subject of Report');
                    // $this->SetKeywords('TCPDF, PDF, example, test, guide');
                    $this->SetFont($jlpdf_bd->font_family_default, '', $jlpdf_bd->font_size_default);
        
                    $orientacao = !empty($jlpdf_bd->orientacao) ? $jlpdf_bd->orientacao : 'P';
                    $size = !empty($jlpdf_bd->size) ? $jlpdf_bd->size : 'A4';
                    
                    $this->SetMargins($jlpdf_bd->margin_left ?? PDF_MARGIN_LEFT, $jlpdf_bd->margin_top ?? PDF_MARGIN_TOP,  $jlpdf_bd->margin_right ?? PDF_MARGIN_RIGHT);
                    $this->SetHeaderMargin(PDF_MARGIN_HEADER);
                    $this->SetFooterMargin(PDF_MARGIN_FOOTER);
                    
                    // de($this::getMargins());
                    
                    if($retornaHtmlApenas === true){
                        if(empty(self::$html_result)){
                            return $html;
                        } else {
                            return self::$html_result;
                        }
                    }
                    
                    $manual_break = false;
                    if (strpos($html, '<jl_newpage>') !== false) {
                        $manual_break = true;
                        // $this->SetAutoPageBreak(false, 0);
                        $arr_page = explode('<jl_newpage>', $html);
                    }
                    
                    // de($jlpdf_bd, $manual_break, $html);
                    
                    
                    if($jlpdf_bd->fl_etiqueta == 'S'){
                        $i = 0;
                        $this->SetAutoPageBreak(false, 0);
                        foreach($arr_page as $page){
                            $this->setPrintFooter(false);
                            
                            // $this->SetMargins(2, 1, 0, true);
                            
                            if($i > 0){
                                $this->AddPage($orientacao , array($size, 30));
                            
                                // de($page);
                            }
                
                            $this->writeHTML($page, true, false, true, false, '');
                            $i++;
                        }
                    } else {
                        if (is_numeric($size)) {
                            
                            $this->setPrintFooter(false);
                            
                            $this->SetMargins(1, PDF_MARGIN_TOP -2, 1, true);
                            $this->SetAutoPageBreak(false, 0);
                            
                            $this->AddPage($orientacao , array($size, 3276));
                
                            $this->writeHTML($html, true, false, true, false, '');
                            
                            $page_height = $this->GetY() + PDF_MARGIN_TOP;
                            
                            $this->deletePage(1); // Remova a página original para redefinir a altura
                            $this->AddPage($orientacao, array($size, $page_height)); // Adicione uma nova página com a altura ajustada
                            
                            $this->writeHTML($html, true, false, true, false, '');
                        } else {
                            if(!$manual_break){
                                $this->setPageFormat($size, $orientacao);
                                $this->AddPage();
                                $this->writeHTML($html, true, false, true, false, '');
                            } else {
                                // de($arr_page);
                                $this->setPageFormat($size, $orientacao);
                                $i = 0;
                                foreach($arr_page as $page){
                                    if($i > 0){
                                        $this->AddPage();
                                    }
                        
                                    $this->writeHTML($page, true, false, true, false, '');
                                    $i++;
                                }
                            }
                        }
                        
                    }
                    
                    
                    $dir_absoluto = realpath('tmp');
                    
                    $nm_file = uniqid() . '.pdf';
                    // de("{$dir_absoluto}/{$nm_file}");
                    
                    $this->Output("{$dir_absoluto}/{$nm_file}", 'F');
                }
            
            TTransaction::close();
            
            return "tmp/{$nm_file}";
            
        } catch (Exception $e){
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
    
    public function generateHTML($jlpdf_nm, $data = []){
        
        return $this->generatePDF($jlpdf_nm, $data, true);
        
    }
    
    public static function sincronizarPdf(&$pdf)
    {
        self::$pdf = &$pdf;
    }
    
    public static function processTemplate($template, $codigo_eval, $data, &$pdf)
    {
        
        // self::$jl_loops_static = $pdf->jl_loops;
        $const = 'constant';
        self::$template = $template;
        
        
        $pattern = '/<include_other(.*?)>(.*?)<\/include_other>/s';
        preg_match_all($pattern, self::$template, $matches, PREG_SET_ORDER);
        
        // de($pattern, self::$template, $matches);
        
        foreach ($matches as $match) {
            $attributesStr = $match[1];
            $content = $match[2];
            
            $attributes = self::extractAttributes($attributesStr);
            $attributes = (object)$attributes;
            
            // $obj_jlpdf_inc = JlpdfBd::where('key_name', '=', $content)->first();
            
            self::sincronizarPdf($pdf);
            $html_processado = self::processInclude($content);
            
            self::$template = str_replace($match[0], $html_processado, self::$template);
            
            // de($attributesStr, $content, $match, $attributes);
        }
        
        if(!empty($data)){
            // Extrair variáveis do array $data
            extract($data);
        }
        
        // Executar código de avaliação (cuidado com a segurança)
        eval($codigo_eval);
        
        /* Se forem utilizado métodos do mecanismo construct, o self::$html_result será diferente de vazio
        Logo ele não seguirá o mecanismo de replace */
        if(!empty(self::$html_result)){
            self::$template = self::$html_result;
        }
        
        if(!empty($data)){
            // Extrair variáveis do array $data
            extract($data);
        }
        
        // Substituir placeholders no template pelos valores do array $data
        foreach ($data as $key => $value) {
            if(validateDate($value)){
                $value = date('d/m/Y H:i:s', strtotime($value));
                LibUtil::consoleLog('validateDate Y-m-d H:i:s -> ' . $value);
            } else {
                if(validateDate($value, 'Y-m-d')){
                    $value = date('d/m/Y', strtotime($value));
                    LibUtil::consoleLog('validateDate Y-m-d -> ' . $value);
                }
            }
            
            self::$template = str_replace('{$' . $key . '}', $value, self::$template);
        }
        
        self::$template = str_replace('{pageNumberThis}', $pdf->getAliasNumPage(), self::$template);
        self::$template = str_replace('{pageNumberQtd}', $pdf->getAliasNbPages(), self::$template);
        
        
        // de($pdf->jlpdf_bd);
        self::$template = str_replace('{jl_logo}', $pdf->jlpdf_bd->logo_img, self::$template);
        
        
        
        
        // Processar QR Code
        $pattern = '/<jl_qrcode(.*?)>(.*?)<\/jl_qrcode>/s';
        preg_match_all($pattern, self::$template, $matches, PREG_SET_ORDER);
        
        // de($matches);
        
        foreach ($matches as $match) {
            $attributesStr = $match[1];
            $content = $match[2];

            // Extrair atributos
            $attributes = self::extractAttributes($attributesStr);
            $attributes = (object)$attributes;
            
            if(!empty($attributes->lb64) and $attributes->lb64 != 'false'){
                $attributes->lb64 = true;
            } else {
                $attributes->lb64 = false;
            }

            // de(explode(',', $attributes->rgb), $attributes->logo, (bool)$attributes->lb64);
            
            $b64qr = self::QR_img(trim($content), explode(',', $attributes->rgb), $attributes->logo, $attributes->lb64);
            
            $img_final = "<img src=\"{$b64qr}\" style=\"{$attributes->style} \">";
            
            // de($match[0], $img_final);

            // Remover a tag <rodape> original do template
            self::$template = str_replace($match[0], $img_final, self::$template);
        }
        /* *********** */
        
        
        // Processar rodapé
        if(!empty(self::$jlFooter)){
            // de(self::$jlFooter);
            $pdf->setRodape(self::$jlFooter);
        } else {
            $pattern = '/<rodape>(.*?)<\/rodape>/s';
            preg_match_all($pattern, self::$template, $matches);
            
            foreach ($matches[1] as $match) {
                $rodape = $match;
                // Substituir placeholders no rodapé
                foreach ($data as $key => $value) {
                    if (!is_array($value)) {
                        $rodape = str_replace('{$' . $key . '}', $value, $rodape);
                    }
                }
                $rodape = str_replace('{PDF_MARGIN_LEFT}', PDF_MARGIN_LEFT, $rodape);
                $pdf->setRodape($rodape);
                self::$template = str_replace('<rodape>' . $match . '</rodape>', '', self::$template);
            }
        }
        
        // Processar cabeçalho
        if(!empty(self::$jlHeader)){
            $pdf->setCabecalho(self::$jlHeader);
        } else {
            $pattern = '/<cabecalho>(.*?)<\/cabecalho>/s';
            preg_match_all($pattern, self::$template, $matches);
            
            foreach ($matches[1] as $match) {
                $cabecalho = $match;
                // Substituir placeholders no cabeçalho
                foreach ($data as $key => $value) {
                    if (!is_array($value)) {
                        $cabecalho = str_replace('{$' . $key . '}', $value, $cabecalho);
                    }
                }
                $cabecalho = str_replace('{PDF_MARGIN_LEFT}', PDF_MARGIN_LEFT, $cabecalho);
                // de($cabecalho);
                $pdf->setCabecalho($cabecalho);
                self::$template = str_replace('<cabecalho>' . $match . '</cabecalho>', '', self::$template);
            }
            // de(self::$template);
        }
        
        return self::$template;
    }
    
    public static function processInclude($key_name, $processHeaderFooter = true)
    {
        
        $obj_jlpdf_inc = JlpdfBd::where('key_name', '=', $key_name)->first();
        $template = $obj_jlpdf_inc->template;
        $codigo_eval = $obj_jlpdf_inc->codigo_eval;
        $data = [];
        $pdf = &self::$pdf;
        
        $const = 'constant';
        
        // Executar código de avaliação (cuidado com a segurança)
        eval($codigo_eval);
        
        if(!empty($data)){
            // Extrair variáveis do array $data
            extract($data);
        }
        
        // Substituir placeholders no template pelos valores do array $data
        foreach ($data as $key => $value) {
            if(validateDate($value)){
                $value = date('d/m/Y H:i:s', strtotime($value));
                LibUtil::consoleLog('validateDate Y-m-d H:i:s -> ' . $value);
            } else {
                if(validateDate($value, 'Y-m-d')){
                    $value = date('d/m/Y', strtotime($value));
                    LibUtil::consoleLog('validateDate Y-m-d -> ' . $value);
                }
            }
            
            $template = str_replace('{$' . $key . '}', $value, $template);
        }
        
        $template = str_replace('{pageNumberThis}', $pdf->getAliasNumPage(), $template);
        $template = str_replace('{pageNumberQtd}', $pdf->getAliasNbPages(), $template);
        
        
        // de($pdf->jlpdf_bd);
        $template = str_replace('{jl_logo}', $pdf->jlpdf_bd->logo_img, $template);
        
        
        
        
        // Processar QR Code
        $pattern = '/<jl_qrcode(.*?)>(.*?)<\/jl_qrcode>/s';
        preg_match_all($pattern, $template, $matches, PREG_SET_ORDER);
        
        // de($matches);
        
        foreach ($matches as $match) {
            $attributesStr = $match[1];
            $content = $match[2];

            // Extrair atributos
            $attributes = self::extractAttributes($attributesStr);
            $attributes = (object)$attributes;
            
            if(!empty($attributes->lb64) and $attributes->lb64 != 'false'){
                $attributes->lb64 = true;
            } else {
                $attributes->lb64 = false;
            }

            // de(explode(',', $attributes->rgb), $attributes->logo, (bool)$attributes->lb64);
            
            $b64qr = self::QR_img(trim($content), explode(',', $attributes->rgb), $attributes->logo, $attributes->lb64);
            
            $img_final = "<img src=\"{$b64qr}\" style=\"{$attributes->style} \">";
            
            // de($match[0], $img_final);

            // Remover a tag <rodape> original do template
            $template = str_replace($match[0], $img_final, $template);
        }
        /* *********** */
        
        if($processHeaderFooter === true){
            // Processar rodapé
            $pattern = '/<rodape>(.*?)<\/rodape>/s';
            preg_match_all($pattern, $template, $matches);
            
            foreach ($matches[1] as $match) {
                $rodape = $match;
                // Substituir placeholders no rodapé
                foreach ($data as $key => $value) {
                    if (!is_array($value)) {
                        $rodape = str_replace('{$' . $key . '}', $value, $rodape);
                    }
                }
                $rodape = str_replace('{PDF_MARGIN_LEFT}', PDF_MARGIN_LEFT, $rodape);
                $pdf->setRodape($rodape);
                $template = str_replace('<rodape>' . $match . '</rodape>', '', $template);
            }
            
            // Processar cabeçalho
            $pattern = '/<cabecalho>(.*?)<\/cabecalho>/s';
            preg_match_all($pattern, $template, $matches);
            
            foreach ($matches[1] as $match) {
                $cabecalho = $match;
                // Substituir placeholders no cabeçalho
                foreach ($data as $key => $value) {
                    if (!is_array($value)) {
                        $cabecalho = str_replace('{$' . $key . '}', $value, $cabecalho);
                    }
                }
                $cabecalho = str_replace('{PDF_MARGIN_LEFT}', PDF_MARGIN_LEFT, $cabecalho);
                // de($cabecalho);
                $pdf->setCabecalho($cabecalho);
                $template = str_replace('<cabecalho>' . $match . '</cabecalho>', '', $template);
            }
            // de($template);
        }
        
        return $template;
    }
    
    public static function setLoop($nmLoop, $arrDados, $obj_param = null){
        
        $functions = !empty($obj_param->functions) ? $obj_param->functions : null;
        $fl_zebrado = !empty($obj_param->fl_zebrado) ? $obj_param->fl_zebrado : null;
        // $fl_extract = isset($obj_param->fl_extract) ? $obj_param->fl_extract : true;
        
        
        $template = self::$template;
        
        $pattern = '/<' . $nmLoop . '>(.*?)<\/' . $nmLoop . '>/s';
        preg_match_all($pattern, $template, $matches);
        
        $i = 0;
        $loop_template = '';
        
        if($obj_param->fl_extract !== false){
            foreach ($arrDados as $k => $v) {
                
                extract($v);
                
            
                foreach ($matches[1] as $match) {
                    // $loop_template = $match;
                    eval('$temp = "' . addslashes($match) . '";');
                }
                
                if($fl_zebrado === true){
        
                    $zebrado_escuro = '#e3e3e3';
                    $zebrado_claro = '#FFF';
                    $cor_bkg = ((($i + 1) % 2) == 0) ? $zebrado_claro : $zebrado_escuro;
            
                    $temp = str_replace('{cor_bkgTR}', (!empty($cor_bkg) ? $cor_bkg : ''), $temp);
                    
                }
                
                $loop_template .= $temp;
                $i++;
                
                // $loop_template = '';
            }
        } else {
            
                $obj = $arrDados;
            
                foreach ($matches[1] as $match) {
                    // $loop_template = $match;
                    eval('$temp = "' . addslashes($match) . '";');
                }
            
                if($fl_zebrado === true){
        
                    $zebrado_escuro = '#e3e3e3';
                    $zebrado_claro = '#FFF';
                    $cor_bkg = ((($i + 1) % 2) == 0) ? $zebrado_claro : $zebrado_escuro;
            
                    $temp = str_replace('{cor_bkgTR}', (!empty($cor_bkg) ? $cor_bkg : ''), $temp);
                    
                }
                
                $loop_template .= $temp;
                $i++;
            }
            
                
        if($obj_param->fl_automaticReplace !== false){
            $template = str_replace($matches[0][0], $loop_template, $template);
        } else {
            $template = str_replace($matches[0][0], $loop_template . PHP_EOL . $matches[0][0], $template);
        }
           
           
        if( ($obj_param->onlyReturn ?? false) !== true){
            
            self::$template = $template;
        
        } else {
            
            return $template;
            
        }
        
    }
    
    public static function removeBlTag($blTag){
        
        $template = self::$template;
        
        $pattern = '/<' . $blTag . '(.*?)>(.*?)<\/' . $blTag . '>/s';
        preg_match_all($pattern, $template, $matches);
        
        $template = str_replace($matches[0][0], '', $template);
        self::$template = $template;
        
    }
    
    
    
    public static function removeTag($tag, $replace = null, $tag_html = null){
        
        if(empty($tag_html)){
            $template = self::$template;
        } else {
            $template = $tag_html;
        }
        
        $pattern = '/<' . $tag . '(.*?)>/s';
        preg_match_all($pattern, $template, $matches);
        
        $template = str_replace($matches[0][0], '', $template);
        self::$template = $template;
        
        
        $template = self::$template;
        
        $pattern = '/<\/' . $tag . '>/s';
        preg_match_all($pattern, $template, $matches);
        
        $template = str_replace($matches[0][0], $replace ?? '', $template);
        
        if(empty($tag_html)){
            self::$template = $template;
        } else {
            return $template;
        }
        
    }
    
    
    public static function getBlTag($blTag){
        $template = self::$template;
        
        $pattern = '/<' . $blTag . '(.*?)>(.*?)<\/' . $blTag . '>/s';
        preg_match_all($pattern, $template, $matches);
        
        return $matches[0][0];
    }
    
    public static function template_file(&$jlpdf_bd){
        
        if($jlpdf_bd->file_template == 'S'){
            
            $dir = 'app/resources/jlpdf/template/';
            
            if (!is_dir($dir)) {
                // Se a pasta não existe, tenta criá-la
                if (mkdir($dir, 0777, true)) {
                    // TToast::show("success", "Pasta '$dir' criada com sucesso.", "topRight", "fas:check-circle");
                } else {
                    throw new Exception("Falha ao criar a pasta '$dir'.");
                }
            }
    
            $file = trim($dir, '/') . "/{$jlpdf_bd->key_name}.html";
            
            if (!file_exists($file)) {
                // Se o arquivo não existe, tenta criá-lo
                if (file_put_contents($file, '') !== false) {
                    // TToast::show("success", "Arquivo '$file' criado com sucesso.", "topRight", "fas:check-circle");
                } else {
                    throw new Exception("Falha ao criar o arquivo '$file'.");
                }
            } else {
                // TToast::show("success", "Arquivo '$file' já existe e o valor foi capturado.", "topRight", "fas:check-circle");
                $jlpdf_bd->template = file_get_contents($file);
                $jlpdf_bd->store();
                return;
            }
            
            if (file_put_contents($file, $jlpdf_bd->template) !== false) {
                // TToast::show("success", "Conteúdo escrito no arquivo '$file' com sucesso.", "topRight", "fas:check-circle");
            } else {
                throw new Exception("Falha ao escrever no arquivo '$file'.");
            }
            
        }
        
    }
    
    public static function eval_file(&$jlpdf_bd){
        
        if($jlpdf_bd->file_eval == 'S'){
            
            $dir = 'app/resources/jlpdf/codigo_eval/';
            
            if (!is_dir($dir)) {
                // Se a pasta não existe, tenta criá-la
                if (mkdir($dir, 0777, true)) {
                    // TToast::show("success", "Pasta '$dir' criada com sucesso.", "topRight", "fas:check-circle");
                } else {
                    throw new Exception("Falha ao criar a pasta '$dir'.");
                }
            }
    
            $file = trim($dir, '/') . "/{$jlpdf_bd->key_name}.php";
            
            if (!file_exists($file)) {
                // Se o arquivo não existe, tenta criá-lo
                if (file_put_contents($file, '') !== false) {
                    // TToast::show("success", "Arquivo '$file' criado com sucesso.", "topRight", "fas:check-circle");
                } else {
                    throw new Exception("Falha ao criar o arquivo '$file'.");
                }
            } else {
                // TToast::show("success", "Arquivo '$file' já existe e o valor foi capturado.", "topRight", "fas:check-circle");
                $jlpdf_bd->codigo_eval = trim( trim( file_get_contents($file) , '<?php' ) , '?>' );
                $jlpdf_bd->store();
                return;
            }
            
            $toPutFile = "<?php " . trim( trim( $jlpdf_bd->codigo_eval , '<?php' ) , '?>' ) . "?>";
            
            if (file_put_contents($file, $toPutFile ) !== false) {
                // TToast::show("success", "Conteúdo escrito no arquivo '$file' com sucesso.", "topRight", "fas:check-circle");
            } else {
                throw new Exception("Falha ao escrever no arquivo '$file'.");
            }
            
        }
        
    }
    
    public static function QR_img($txt = 'https://github.com/JLPDF/JLPDF', $rgb = [10, 54, 83], $logo = null, $fl_logo_b64 = false){
        // Depende da LIB 
        // 10, 54, 83
        // 179, 51, 54
        
        $options = new QROptions;

        $options->version             = QRCode::VERSION_AUTO;
        $options->outputBase64        = true;
        $options->scale               = 6;
        $options->imageTransparent    = false;
        $options->drawCircularModules = true;
        $options->circleRadius        = 0.45;
        $options->keepAsSquare        = [
        	QRMatrix::M_FINDER,
        	QRMatrix::M_FINDER_DOT,
        ];

        $options->moduleValues = [
            // finder
            QRMatrix::M_FINDER_DARK    => $rgb, // Azul escuro
            QRMatrix::M_FINDER_DOT     => $rgb, // Ponto do finder azul
            QRMatrix::M_FINDER         => [233, 233, 233], // Branco
            // alignment
            QRMatrix::M_ALIGNMENT_DARK => $rgb, // Azul
            QRMatrix::M_ALIGNMENT      => [233, 233, 233], // Branco
            // timing
            QRMatrix::M_TIMING_DARK    => $rgb, // Azul
            QRMatrix::M_TIMING         => [233, 233, 233], // Branco
            // format
            QRMatrix::M_FORMAT_DARK    => $rgb, // Azul
            QRMatrix::M_FORMAT         => [233, 233, 233], // Branco
            // version
            QRMatrix::M_VERSION_DARK   => $rgb, // Azul
            QRMatrix::M_VERSION        => [233, 233, 233], // Branco
            // data
            QRMatrix::M_DATA_DARK      => $rgb, // Azul
            QRMatrix::M_DATA           => [233, 233, 233], // Branco
            // darkmodule
            QRMatrix::M_DARKMODULE     => $rgb, // Azul
            // separator
            QRMatrix::M_SEPARATOR      => [233, 233, 233], // Branco
            // quietzone
            QRMatrix::M_QUIETZONE      => [233, 233, 233], // Branco
            // logo (requer um espaço para logo configurado)
            QRMatrix::M_LOGO           => [233, 233, 233], // Branco
        ];

        // ecc level H is required for logo space
        $options->eccLevel            = EccLevel::H;
        $options->addLogoSpace        = true;
        $options->logoSpaceWidth      = 13;
        $options->logoSpaceHeight     = 13;
        $options->addQuietzone        = false;

        $qrcode = new QRCode($options);
        $qrcode->addByteSegment($txt);

        $qrOutputInterface = new QRImageWithLogo($options, $qrcode->getQRMatrix());
        
        if(empty($logo)){
            $fl_logo_b64 = true;
            
            $logo = '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WQanjHoInholZycShPsCFfyXLkhs4WL7CRnTzlzMW0zAGWZm567ensk73fuZWy684VWbFrvP/PVNvzvEOoFWKfNb5rnxxt+y8c7bufnmm7jzro2YXi8UqosYZ8qCNG9T9fvM5C00AShU9EuSTJPphMqKET2WoawUI762iB8bYr3dMLlUuCW623QErBhpCzLC6EyoJROTPn7mpb+5yCQ2nseNGElCCBLVusde35PffeqDP3H61z/rUVTOIhM9TGVLNb3FebSsG8Pa6AHxw3ZPk8afH633uHwdRx8rz043HleOOk+nCEdQE8zdcs5rbrzxJgAAIABJREFUHM4PEDKjcgXoLDa5V6FpKJBq6G7duvVz73zlmWu53Onez9xw6W0fvOic655RObdOJjl2UHLLdddz5U8v5dbrf0vR70Ei8SJ4QRKAmDYm3P/H3pvH3XZUdd7fGvbeZ3iGe28GQhIQUOaAE20LIo22MtmojQoi+go2iC0oiIIQxWYIgoBNY2tkUITQzAZQMMhkGDokEELCBZJAZshwk5vc4RnO2UMN/UdV7bPPec4z3Anb930rn53z3DPsU6d27VWr1vqt388hsgxpG1SuA6eGt6BA9zMQAosHGer2JrzbyVqkMFWyNFuY0EjmOI8ZzEOQTTEG1XHZnHPkUqKcQ8eFzopuRXaQz5sORsnulG4ngnMGqTKcDyT72MApbozFnCC7/bLzzjv13L9878fJekMlYszZBsphvKUpLXnWw3kT94SiDevJROsoE867C+tNRS/zvze4Mz6OSoRUpw1pO9m33maIDu+kkhJv529Wde0rSQShB9bUVN0aLJRSGbYp8UqyuDhcX9vBwP3PL+179Zc/cfULGyWVz3L27d/HlVdcwZVXXIG76yALRcFAaoTy2MhzNOt6tZ3txoNnXt8YuE81dtPWuFs04KdYttMbNlrvDS1wvEXtxwmXOdGF6GZ3ZVuc4CfL+FwXxbXW3MU098SnDzShZidpziNsf/X+CxfOftWrPi90dgZZnNixdd0KH5U2/NRGcNJ34ZjSKYIOuT/JSIXPTTaEbuax02ZWvZ1knydIxTlJHFv2fSgRm4ShUrBeeIFSAbdtmwo9R7Gi297vff/az137D7etHvxpKzzj1TU+9o8fZf++2xkfOkShFdmgT12tc9A5il6GNzZYwznFybM/YifPfbfbsfah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';
        }
        
        $nm_file = "";
        if($fl_logo_b64){
            $nm_file = "tmp/" . uniqid() . ".png";
            
            file_put_contents($nm_file, base64_decode($logo));
        } else {
            if(!empty($logo)){
                $nm_file = $logo;
            }
        }
        
        // de($nm_file);

        $out = $qrOutputInterface->dump(null, $nm_file ?? '');
        
        return $out;
        
    }
    
    private static function extractAttributes($tag) {
        $pattern = '/(\w+)="([^"]*)"/';
        preg_match_all($pattern, $tag, $matches, PREG_SET_ORDER);

        $attributes = [];
        foreach ($matches as $match) {
            $attributes[$match[1]] = $match[2];
        }

        return $attributes;
    }
    
    
    
    public static function addReportFile($nome){
        return self::processInclude($nome, false);
    }
    
    public static function setJlHeader($html){
        self::$jlHeader = $html;
    }
    
    public static function setJlFooter($html){
        self::$jlFooter = $html;
    }
    
    public static function addString($str){
        self::$html_result .= $str;
    }
    
    public static function newPage(){
        self::$html_result .= "<jl_newpage>";
    }
    
    public static function tagToHTML($tag, $objeto)
    {
        //--> obtem o conteúdo Html da tag relacionada no parâmetro
        $tagHTML = self::getBlTag($tag);
        
        //--> realiza o replace do HTML pelas propriedades do objeto
        eval('$temp = "' . addslashes($tagHTML) . '";');
        
        //--> remove a tag passada da string
        return self::removeHTMLTag($tag,$temp);
       
    }
    
    public static function addHtmlTag($tag) {
      // Obtém o HTML com a tag especificada
        $tagHTML = self::getBlTag($tag);
        // Remove espaços extras e quebras de linha
        $tagHTML = trim($tagHTML);
        // Escapa caracteres especiais para uso em expressão regular
        $tag = preg_quote($tag, '/');
        // Define o padrão de regex para encontrar a tag e seu conteúdo
        $pattern = '/<' . $tag . '>(.*?)<\/' . $tag . '>/si';
        // Aplica a regex para encontrar a tag e seu conteúdo
        preg_match($pattern, $tagHTML, $matches);
        // Retorna o conteúdo encontrado dentro da tag
        return isset($matches[1]) ? $matches[1] : '';
    }

    public static function removeHTMLTag($tag, $string) {
        // Constrói o padrão regex para a tag de abertura e fechamento a ser removida
        $pattern = [
            "/<$tag\b[^>]*>/i",    // Tag de abertura
            "/<\/$tag>/i"          // Tag de fechamento
        ];

        // Remove as tags especificadas mantendo o conteúdo interno
        return preg_replace($pattern, '', $string);
    }
    
    
    public static function addHtml($html){
        self::$html_result .= $html;
    }
    
    public static function commitReport(){
        if(!empty(self::$html_result)){
            self::$template = self::$html_result;
        }
        // return self::processTemplate(self::$template, '', [], self::$pdf);
    }
    
    public static function loopToHTML($tag, $objeto){
        
        $result = '';
        $txtTag = $tag;
        foreach ($objeto as $obj) {
            $result .= self::tagToHTML($tag, $obj);
        }
        
        return $result;
        
    }
    
    /* FIM - Metodos JLPDF */
    
    /* INICIO - Metodos TCPDF */
    
    public function Header() {
        // de($this->cabecalho_html);
        $this->writeHTMLCell(0, 0, '', '0', $this->cabecalho_html, 0, 0, 0, true, '', true);
        // $this->writeHTML($this->cabecalho_html, true, false, true, false, '');
    }
    
    public function setCabecalho($cabecalho_html){
        $this->cabecalho_html = $cabecalho_html;
    }
    
    public function Footer() {
        // $this->writeHTML($this->footer_html, true, false, true, false, '');
        if(!empty($this->footer_html_mb)){
            $pageHeight = $this->getPageHeight();
            $this->SetY($pageHeight - $this->footer_html_mb);
        }
        $this->writeHTMLCell(0, 0, '', '', $this->footer_html, 0, 0, 0, true, 'C', true);
    }
    
    public function setRodape($footerHtml){
        $this->footer_html = $footerHtml;
    }
    public function setRodapeMargem($margem){
        $this->footer_html_mb = $margem;
    }
    
    /* FIM - Metodos TCPDF */
}


/* Classe para QRcode */
class QRImageWithLogo extends QRGdImagePNG{

	/**
	 * @param string|null $file
	 * @param string|null $logo
	 *
	 * @return string
	 * @throws \chillerlan\QRCode\Output\QRCodeOutputException
	 */
	public function dump(string $file = null, string $logo = null): string {
        $this->options->returnResource = true;

        if (!is_file($logo) || !is_readable($logo)) {
            throw new QRCodeOutputException('Invalid logo file');
        }

        parent::dump($file);

        $im = imagecreatefrompng($logo);
        $w = imagesx($im);
        $h = imagesy($im);

        $lw = (($this->options->logoSpaceWidth - 2) * $this->options->scale);
        $lh = (($this->options->logoSpaceHeight - 2) * $this->options->scale);
        $ql = ($this->matrix->getSize() * $this->options->scale);

        imagecopyresampled($this->image, $im, (($ql - $lw) / 2), (($ql - $lh) / 2), 0, 0, $lw, $lh, $w, $h);
        $imageData = $this->dumpImage();

        if ($this->options->outputBase64) {
            ob_start();
            imagepng($this->image);
            $rawImage = ob_get_clean();
            $imageData = base64_encode($rawImage);
            return 'data:image/png;base64,' . $imageData;
        }

        $this->saveToFile($imageData, $file);
        return $imageData;
    }

}
/* **************** */
