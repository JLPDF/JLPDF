<?php

class JlpdfBdForm_ extends TPage
{
    protected $form;
    private $formFields = [];
    private static $database = 'clinica';
    private static $activeRecord = 'JlpdfBd';
    private static $primaryKey = 'id';
    private static $formName = 'form_JlpdfBdForm';

    use Adianti\Base\AdiantiFileSaveTrait;

    /**
     * Form constructor
     * @param $param Request
     */
    public function __construct( $param )
    {
        parent::__construct();

        if(!empty($param['target_container']))
        {
            $this->adianti_target_container = $param['target_container'];
        }

        // creates the form
        $this->form = new BootstrapFormBuilder(self::$formName);
        // define the form title
        $this->form->setFormTitle("Cadastro de JLPDF");


        $id = new TEntry('id');
        $ativo = new THidden('ativo');
        $file_template = new TCheckButton('file_template');
        $file_eval = new TCheckButton('file_eval');
        $key_name = new TEntry('key_name');
        $logo_img = new TImageCropper('logo_img');
        $fl_etiqueta = new TRadioGroup('fl_etiqueta');
        $orientacao = new TEntry('orientacao');
        $font_size_default = new TEntry('font_size_default');
        $font_family_default = new TEntry('font_family_default');
        $size = new TEntry('size');
        $altura_doc = new TEntry('altura_doc');
        $margin_left = new TEntry('margin_left');
        $margin_top = new TEntry('margin_top');
        $margin_right = new TEntry('margin_right');
        $template = new TText('template');
        $codigo_eval = new TText('codigo_eval');

        $file_template->setChangeAction(new TAction([$this,'onChangeFileTemplate']));
        $file_eval->setChangeAction(new TAction([$this,'onChangeFileEval']));

        $font_size_default->addValidation("Font size default", new TRequiredValidator()); 
        $font_family_default->addValidation("Font family default", new TRequiredValidator()); 
        $size->addValidation("Size", new TRequiredValidator()); 
        $template->addValidation("Template", new TRequiredValidator()); 
        $codigo_eval->addValidation("Codigo eval", new TRequiredValidator()); 

        $id->setEditable(false);
        $logo_img->enableFileHandling();
        $logo_img->setAllowedExtensions(["jpg","jpeg","png"]);
        $logo_img->setImagePlaceholder(new TImage("fas:file-upload #000000"));
        $fl_etiqueta->addItems(["S"=>"Sim","N"=>"Não"]);
        $fl_etiqueta->setLayout('horizontal');
        $fl_etiqueta->setUseButton();
        $fl_etiqueta->setBreakItems(2);
        $size->setTip("A4, A5, 72, 50, etc");
        $altura_doc->setMask('9999');
        $file_eval->setUseSwitch(true, 'blue');
        $file_template->setUseSwitch(true, 'blue');

        $file_eval->setIndexValue("S");
        $file_template->setIndexValue("S");

        $file_eval->setInactiveIndexValue("N");
        $file_template->setInactiveIndexValue("N");

        $id->setMaxLength(36);
        $size->setMaxLength(2);
        $key_name->setMaxLength(50);
        $orientacao->setMaxLength(1);
        $font_family_default->setMaxLength(50);

        $ativo->setValue('S');
        $size->setValue('A4');
        $file_eval->setValue('N');
        $orientacao->setValue('P');
        $file_template->setValue('N');
        $font_size_default->setValue('10');
        $font_family_default->setValue('helvetica');

        $id->setSize('100%');
        $ativo->setSize(200);
        $size->setSize('100%');
        $fl_etiqueta->setSize(80);
        $key_name->setSize('100%');
        $orientacao->setSize('100%');
        $altura_doc->setSize('100%');
        $margin_top->setSize('100%');
        $margin_left->setSize('100%');
        $logo_img->setSize('100%', 80);
        $margin_right->setSize('100%');
        $template->setSize('100%', 400);
        $codigo_eval->setSize('100%', 400);
        $font_size_default->setSize('100%');
        $font_family_default->setSize('100%');

        $row1 = $this->form->addFields([new TLabel("Id:", null, '14px', null, '100%'),$id,$ativo],[new TLabel("Template File:", null, '14px', null, '100%'),$file_template],[new TLabel("Código File:", null, '14px', null, '100%'),$file_eval]);
        $row1->layout = ['col-sm-6',' col-sm-3',' col-sm-3'];

        $row2 = $this->form->addFields([new TLabel("Key name:", null, '14px', null, '100%'),$key_name],[new TLabel("Logo img:", null, '14px', null, '100%'),$logo_img]);
        $row2->layout = [' col-sm-6',' col-sm-6'];

        $row3 = $this->form->addFields([new TLabel("Etiqueta:", null, '14px', null, '100%'),$fl_etiqueta],[new TLabel("Orientação:", null, '14px', null, '100%'),$orientacao],[new TLabel("Font size default:", '#ff0000', '14px', null, '100%'),$font_size_default],[new TLabel("Font family default:", '#ff0000', '14px', null, '100%'),$font_family_default]);
        $row3->layout = [' col-sm-3',' col-sm-3',' col-sm-3',' col-sm-3'];

        $row4 = $this->form->addFields([new TLabel("Size:", '#ff0000', '14px', null, '100%'),$size],[new TLabel("Altura:", null, '14px', null, '100%'),$altura_doc],[new TLabel("Margin Left:", null, '14px', null, '100%'),$margin_left],[new TLabel("Margin Top:", null, '14px', null),$margin_top],[new TLabel("Margin Right:", null, '14px', null),$margin_right],[]);
        $row4->layout = ['col-sm-2','col-sm-2','col-sm-2','col-sm-2','col-sm-2','col-sm-2'];

        $row5 = $this->form->addFields([new TLabel("Template:", '#ff0000', '14px', null, '100%'),$template]);
        $row5->layout = [' col-sm-12'];

        $row6 = $this->form->addFields([new TLabel("Codigo eval:", '#ff0000', '14px', null, '100%'),$codigo_eval]);
        $row6->layout = ['col-sm-12'];

        // create the form actions
        $btn_onsave = $this->form->addAction("Salvar", new TAction([$this, 'onSave']), 'fas:save #ffffff');
        $this->btn_onsave = $btn_onsave;
        $btn_onsave->addStyleClass('btn-primary'); 

        $btn_onshow = $this->form->addAction("Voltar", new TAction(['JlpdfBdList', 'onShow']), 'fas:arrow-left #000000');
        $this->btn_onshow = $btn_onshow;

        parent::setTargetContainer('adianti_right_panel');

        $btnClose = new TButton('closeCurtain');
        $btnClose->class = 'btn btn-sm btn-default';
        $btnClose->style = 'margin-right:10px;';
        $btnClose->onClick = "Template.closeRightPanel();";
        $btnClose->setLabel("Fechar");
        $btnClose->setImage('fas:times');

        $this->form->addHeaderWidget($btnClose);

        if(!empty($param['id'])){
            TTransaction::open(MAIN_DATABASE);
                $arr_jlpdfBd = JlpdfBd::find($param['id'])->toArray();
            TTransaction::close();
            self::enabDisabAreaTemplate($arr_jlpdfBd);
            self::enabDisabAreaEval($arr_jlpdfBd);
        }

        parent::add($this->form);

    }

    public static function onChangeFileTemplate($param = null) 
    {
        try 
        {
            //code here
            self::enabDisabAreaTemplate($param);

        }
        catch (Exception $e) 
        {
            new TMessage('error', $e->getMessage());    
        }
    }

    public static function onChangeFileEval($param = null) 
    {
        try 
        {
            //code here
            self::enabDisabAreaEval($param);

        }
        catch (Exception $e) 
        {
            new TMessage('error', $e->getMessage());    
        }
    }

    public function onSave($param = null) 
    {
        try
        {
            TTransaction::open(self::$database); // open a transaction

            $messageAction = null;

            $this->form->validate(); // validate form data

            $object = new JlpdfBd(); // create an empty object 

            $data = $this->form->getData(); // get form data as array
            $object->fromArray( (array) $data); // load the object with data

            $logo_img_dir = 'app/outputs/img_jlpdf';  

            JLPDF::template_file($object);
            JLPDF::eval_file($object);

            $object->store(); // save the object 

            $this->saveFile($object, $data, 'logo_img', $logo_img_dir);
            $loadPageParam = [];

            if(!empty($param['target_container']))
            {
                $loadPageParam['target_container'] = $param['target_container'];
            }

            // get the generated {PRIMARY_KEY}
            $data->id = $object->id; 

            $this->form->setData($data); // fill form data
            TTransaction::close(); // close the transaction

            TToast::show('success', "Registro salvo", 'topRight', 'far:check-circle');
            TApplication::loadPage('JlpdfBdList', 'onShow', $loadPageParam); 

                        TScript::create("Template.closeRightPanel();"); 

        }
        catch (Exception $e) // in case of exception
        {
            //</catchAutoCode> 

            new TMessage('error', $e->getMessage()); // shows the exception error message
            $this->form->setData( $this->form->getData() ); // keep form data
            TTransaction::rollback(); // undo all pending operations
        }
    }

    public function onEdit( $param )
    {
        try
        {
            if (isset($param['key']))
            {
                $key = $param['key'];  // get the parameter $key
                TTransaction::open(self::$database); // open a transaction

                $object = new JlpdfBd($key); // instantiates the Active Record 

                $this->form->setData($object); // fill the form 

                TTransaction::close(); // close the transaction 
            }
            else
            {
                $this->form->clear();
            }
        }
        catch (Exception $e) // in case of exception
        {
            new TMessage('error', $e->getMessage()); // shows the exception error message
            TTransaction::rollback(); // undo all pending operations
        }
    }

    /**
     * Clear form data
     * @param $param Request
     */
    public function onClear( $param )
    {
        $this->form->clear(true);

    }

    public function onShow($param = null)
    {

    } 

    public static function getFormName()
    {
        return self::$formName;
    }

    public static function enabDisabAreaTemplate($param){
        if($param['file_template'] == 'S'){
            TText::disableField(self::$formName, 'template');
        } else {
            TText::enableField(self::$formName, 'template');
        }
    }

    public static function enabDisabAreaEval($param){
        if($param['file_eval'] == 'S'){
            TText::disableField(self::$formName, 'codigo_eval');
        } else {
            TText::enableField(self::$formName, 'codigo_eval');
        }
    }

}

