CREATE TABLE jlpdf_bd( 
      `id` varchar  (36)   NOT NULL  , 
      `dt_cadastro` datetime   NOT NULL  , 
      `dt_exclusao` datetime   , 
      `dt_alteracao` datetime   , 
      `ativo` char  (1)   NOT NULL    DEFAULT 'S', 
      `logo_img` text   , 
      `size` char  (2)   NOT NULL    DEFAULT 'A4', 
      `orientacao` char  (1)     DEFAULT 'P', 
      `template` text   NOT NULL  , 
      `codigo_eval` text   NOT NULL  , 
      `key_name` varchar  (50)   , 
      `font_size_default` int   NOT NULL    DEFAULT 10, 
      `font_family_default` varchar  (50)   NOT NULL    DEFAULT 'helvetica', 
      `fl_etiqueta` char  (1)     DEFAULT 'N', 
 PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci; 

 
 ALTER TABLE jlpdf_bd ADD UNIQUE (key_name);
 
