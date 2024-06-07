<?php

class JLPDF extends TCPDF
{
    private static $template = '';
    private static $jl_loops_static = [];
    
    /* NÃO PODE TER __CONSTRUCT, POIS CAUSA CONFLITO COM O __CONSTRUCT DO TCPDF */
    
    /* INICIO - Metodos JLPDF */
    
    public function generatePDF($jlpdf_nm, $data)
    {
        self::$jl_loops_static = $pdf->jl_loops;
        TTransaction::open(MAIN_DATABASE);
        
            $jlpdf_bd = JlpdfBd::where('key_name', '=', $jlpdf_nm)->first();
    
            if ($jlpdf_bd) {
                
                
                $jlpdf_bd->orientacao = !empty($jlpdf_bd->orientacao) ? $jlpdf_bd->orientacao : 'P';
                $this->setPageOrientation($jlpdf_bd->orientacao);
                $this->jlpdf_bd = $jlpdf_bd;
                
                $html = self::processTemplate($jlpdf_bd->template, $jlpdf_bd->codigo_eval, $data, $this);
                
                // d($html);
                
                $this->SetCreator(PDF_CREATOR);
                $this->SetAuthor('Author Name');
                $this->SetTitle('Sample Report');
                $this->SetSubject('Subject of Report');
                $this->SetKeywords('TCPDF, PDF, example, test, guide');
                $this->SetFont($jlpdf_bd->font_family_default, '', $jlpdf_bd->font_size_default);
    
                $orientacao = !empty($jlpdf_bd->orientacao) ? $jlpdf_bd->orientacao : 'P';
                $size = !empty($jlpdf_bd->size) ? $jlpdf_bd->size : 'A4';
                
                $this->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
                $this->SetHeaderMargin(PDF_MARGIN_HEADER);
                $this->SetFooterMargin(PDF_MARGIN_FOOTER);
    
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
                    $this->setPageFormat($size, $orientacao);
                    $this->AddPage();
                    $this->writeHTML($html, true, false, true, false, '');
                }
                
                // if(is_numeric($size)){
                //     // Remova os cabeçalhos e rodapés automáticos
                //     $this->setPrintHeader(false);
                //     $this->setPrintFooter(false);
                    
                //     // Defina as margens
                //     $this->SetMargins(0, 0, 0);
                    
                //     $page_height = $this->GetY();
                //     $this->setPageFormat(array(72, $page_height));
                // }
                
                $dir_absoluto = realpath('tmp');
                
                $nm_file = uniqid() . '.pdf';
                // de("{$dir_absoluto}/{$nm_file}");
                
                $this->Output("{$dir_absoluto}/{$nm_file}", 'F');
            }
        
        TTransaction::close();
        
        return "tmp/{$nm_file}";
    }

    public static function processTemplate($template, $codigo_eval, $data, $pdf)
    {
        
        // self::$jl_loops_static = $pdf->jl_loops;
        
        self::$template = $template;
        
        // Extrair variáveis do array $data
        extract($data);
        
        // Executar código de avaliação (cuidado com a segurança)
        eval($codigo_eval);
        
        // Substituir placeholders no template pelos valores do array $data
        foreach ($data as $key => $value) {
            self::$template = str_replace('{$' . $key . '}', $value, self::$template);
        }
        
        self::$template = str_replace('{pageNumberThis}', $pdf->getAliasNumPage(), self::$template);
        self::$template = str_replace('{pageNumberQtd}', $pdf->getAliasNbPages(), self::$template);
        
        
        // de($pdf->jlpdf_bd);
        self::$template = str_replace('{jl_logo}', $pdf->jlpdf_bd->logo_img, self::$template);
        
        $const = 'constant';
        
        
        // Processar rodapé
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
        
        // Processar cabeçalho
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
            $pdf->setCabecalho($cabecalho);
            self::$template = str_replace('<cabecalho>' . $match . '</cabecalho>', '', self::$template);
        }
        
        return self::$template;
    }
    
    public static function setLoop($nmLoop, $arrDados, $obj_param = null){
        
        $functions = !empty($obj_param->functions) ? $obj_param->functions : null;
        $fl_zebrado = !empty($obj_param->fl_zebrado) ? $obj_param->fl_zebrado : null;
        
        $template = self::$template;
        
        $pattern = '/<' . $nmLoop . '>(.*?)<\/' . $nmLoop . '>/s';
        preg_match_all($pattern, $template, $matches);
        
        $i = 0;
        $loop_template = '';
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
        $template = str_replace($matches[0], $loop_template, $template);
        
        self::$template = $template;
        
    }
    
    /* FIM - Metodos JLPDF */
    
    /* INICIO - Metodos TCPDF */
    
    public function Header() {
        // de($this->cabecalho_html);
        $this->writeHTMLCell(0, 0, '', '', $this->cabecalho_html, 0, 1, 0, true, '', true);
    }
    
    public function setCabecalho($cabecalho_html){
        $this->cabecalho_html = $cabecalho_html;
    }
    
    public function Footer() {
        $this->writeHTML($this->footer_html, true, false, true, false, '');
    }
    
    public function setRodape($footerHtml){
        $this->footer_html = $footerHtml;
    }
    
    /* FIM - Metodos TCPDF */
}
