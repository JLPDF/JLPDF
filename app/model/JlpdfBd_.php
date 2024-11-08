<?php

//<fileHeader>

//</fileHeader>

class JlpdfBd extends TRecord
{
    const TABLENAME  = 'jlpdf_bd';
    const PRIMARYKEY = 'id';
    const IDPOLICY   =  'uuid'; // {max, serial}
    

    
    const DELETEDAT  = 'dt_exclusao';
    const CREATEDAT  = 'dt_cadastro';
    const UPDATEDAT  = 'dt_alteracao';
    
    
    
    //<classProperties>

    //</classProperties>
    
    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('dt_cadastro');
        parent::addAttribute('dt_exclusao');
        parent::addAttribute('dt_alteracao');
        parent::addAttribute('ativo');
        parent::addAttribute('logo_img');
        parent::addAttribute('size');
        parent::addAttribute('orientacao');
        parent::addAttribute('template');
        parent::addAttribute('codigo_eval');
        parent::addAttribute('key_name');
        parent::addAttribute('font_size_default');
        parent::addAttribute('font_family_default');
        //<onAfterConstruct>

        //</onAfterConstruct>
    }

    

    
    //<userCustomFunctions>

    //</userCustomFunctions>
}

