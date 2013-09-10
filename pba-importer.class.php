<?php

class PbaImporter
{
	var $local_version;
	var $custom_colors;
	var $plugin_url;
	var $options;
	var $key;

	function PbaImporter()
	{
		$this->local_version = '0.5';

		$this->plugin_url = defined('WP_PLUGIN_URL') ?
							trailingslashit(WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__))) :
							trailingslashit(get_bloginfo('wpurl')) . PLUGINDIR . '/' . dirname(plugin_basename(__FILE__));

		$this->key = 'pba-importer';

		$this->options = $this->get_options();

		$this->add_filters_and_hooks();
	}

	function add_filters_and_hooks()
	{
		add_filter('the_content', array($this, 'check'), 100);
		add_filter('the_excerpt', array($this, 'check'), 100);

		add_action('plugins_loaded', array($this, 'install'));
		//add_action('after_plugin_row', array($this, 'check_plugin_version'));

		//add_action('wp_head', array($this, 'addHeaderCode'), 1);

		add_action('admin_head', array( $this, 'plugin_header' ) );

		add_action('admin_menu', array($this, 'add_menu_items'));

		register_activation_hook(__FILE__, array($this, 'install'));

		wp_schedule_event(time(), 'hourly', 'cron_update_plans');
	}	
	
	function cron_update_plans() {
		$hspc_url = $this->options['hspc_url'];
		$hspc_login = $this->options['hspc_login'];
		$hspc_pass = $this->options['hspc_pass'];

		$HspcApi = new HspcApi($hspc_url, $hspc_login, $hspc_pass);
		$planos = $HspcApi->get_planos();
		
		$this->update_planos($planos);
	}

	function addHeaderCode() {
/* 		echo '<link type="text/css" rel="stylesheet" href="' . get_bloginfo('wpurl') . '/wp-content/plugins/listavip/css/message.min.css" />' . "\n"; */
			//if (function_exists('wp_enqueue_script')) {
			//	wp_enqueue_script('devlounge_plugin_series', get_bloginfo('wpurl') . '/wp-content/plugins/devlounge-plugin-series/js/devlounge-plugin-series.js', array('prototype'), '0.1');
			//}
//			$devOptions = $this->getAdminOptions();
//			if ($devOptions['show_header'] == "false") { return; }

		echo '
		<script type="text/javascript">
		<!--
			
			if(typeof jQuery == \'undefined\'){
				document.write(\'<script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>\');
			}

		-->
		</script>
		';

	}
	
	
	function plugin_header() {
		echo '
		<style>
		#icon-pba-importer_settings { background:transparent url(\'' . $this->plugin_url .'i/logo.jpg' . '\') no-repeat; }
		
		.row-fluid {
  width: 100%;
  *zoom: 1;
}

.row-fluid:before,
.row-fluid:after {
  display: table;
  line-height: 0;
  content: "";
}

.row-fluid:after {
  clear: both;
}

.row-fluid [class*="span"] {
  display: block;
  float: left;
  width: 100%;
  min-height: 30px;
  margin-left: 2.127659574468085%;
  *margin-left: 2.074468085106383%;
  -webkit-box-sizing: border-box;
     -moz-box-sizing: border-box;
          box-sizing: border-box;
}

.row-fluid [class*="span"]:first-child {
  margin-left: 0;
}

.row-fluid .controls-row [class*="span"] + [class*="span"] {
  margin-left: 2.127659574468085%;
}

.row-fluid .span12 {
  width: 100%;
  *width: 99.94680851063829%;
}

.row-fluid .span11 {
  width: 91.48936170212765%;
  *width: 91.43617021276594%;
}

.row-fluid .span10 {
  width: 82.97872340425532%;
  *width: 82.92553191489361%;
}

.row-fluid .span9 {
  width: 74.46808510638297%;
  *width: 74.41489361702126%;
}

.row-fluid .span8 {
  width: 65.95744680851064%;
  *width: 65.90425531914893%;
}

.row-fluid .span7 {
  width: 57.44680851063829%;
  *width: 57.39361702127659%;
}

.row-fluid .span6 {
  width: 48.93617021276595%;
  *width: 48.88297872340425%;
}

.row-fluid .span5 {
  width: 40.42553191489362%;
  *width: 40.37234042553192%;
}

.row-fluid .span4 {
  width: 31.914893617021278%;
  *width: 31.861702127659576%;
}

.row-fluid .span3 {
  width: 23.404255319148934%;
  *width: 23.351063829787233%;
}

.row-fluid .span2 {
  width: 14.893617021276595%;
  *width: 14.840425531914894%;
}

.row-fluid .span1 {
  width: 6.382978723404255%;
  *width: 6.329787234042553%;
}

.row-fluid .offset12 {
  margin-left: 104.25531914893617%;
  *margin-left: 104.14893617021275%;
}

.row-fluid .offset12:first-child {
  margin-left: 102.12765957446808%;
  *margin-left: 102.02127659574467%;
}

.row-fluid .offset11 {
  margin-left: 95.74468085106382%;
  *margin-left: 95.6382978723404%;
}

.row-fluid .offset11:first-child {
  margin-left: 93.61702127659574%;
  *margin-left: 93.51063829787232%;
}

.row-fluid .offset10 {
  margin-left: 87.23404255319149%;
  *margin-left: 87.12765957446807%;
}

.row-fluid .offset10:first-child {
  margin-left: 85.1063829787234%;
  *margin-left: 84.99999999999999%;
}

.row-fluid .offset9 {
  margin-left: 78.72340425531914%;
  *margin-left: 78.61702127659572%;
}

.row-fluid .offset9:first-child {
  margin-left: 76.59574468085106%;
  *margin-left: 76.48936170212764%;
}

.row-fluid .offset8 {
  margin-left: 70.2127659574468%;
  *margin-left: 70.10638297872339%;
}

.row-fluid .offset8:first-child {
  margin-left: 68.08510638297872%;
  *margin-left: 67.9787234042553%;
}

.row-fluid .offset7 {
  margin-left: 61.70212765957446%;
  *margin-left: 61.59574468085106%;
}

.row-fluid .offset7:first-child {
  margin-left: 59.574468085106375%;
  *margin-left: 59.46808510638297%;
}

.row-fluid .offset6 {
  margin-left: 53.191489361702125%;
  *margin-left: 53.085106382978715%;
}

.row-fluid .offset6:first-child {
  margin-left: 51.063829787234035%;
  *margin-left: 50.95744680851063%;
}

.row-fluid .offset5 {
  margin-left: 44.68085106382979%;
  *margin-left: 44.57446808510638%;
}

.row-fluid .offset5:first-child {
  margin-left: 42.5531914893617%;
  *margin-left: 42.4468085106383%;
}

.row-fluid .offset4 {
  margin-left: 36.170212765957444%;
  *margin-left: 36.06382978723405%;
}

.row-fluid .offset4:first-child {
  margin-left: 34.04255319148936%;
  *margin-left: 33.93617021276596%;
}

.row-fluid .offset3 {
  margin-left: 27.659574468085104%;
  *margin-left: 27.5531914893617%;
}

.row-fluid .offset3:first-child {
  margin-left: 25.53191489361702%;
  *margin-left: 25.425531914893618%;
}

.row-fluid .offset2 {
  margin-left: 19.148936170212764%;
  *margin-left: 19.04255319148936%;
}

.row-fluid .offset2:first-child {
  margin-left: 17.02127659574468%;
  *margin-left: 16.914893617021278%;
}

.row-fluid .offset1 {
  margin-left: 10.638297872340425%;
  *margin-left: 10.53191489361702%;
}

.row-fluid .offset1:first-child {
  margin-left: 8.51063829787234%;
  *margin-left: 8.404255319148938%;
}

[class*="span"].hide,
.row-fluid [class*="span"].hide {
  display: none;
}

[class*="span"].pull-right,
.row-fluid [class*="span"].pull-right {
  float: right;
}
		
		</style>
		';
	}

	function add_menu_items()
	{
	    //add_menu_page('PBA Importer', 'PBA Importer', 5, __FILE__, array($this, "options_page"));

		$image = $this->plugin_url . '/i/icon.png';

		add_menu_page( __( 'PBA Importer', 'pba-importer' ), __( 'PBA Importer', 'pba-importer' ), 'manage_options', 'pba-importer_settings', array(
			&$this,
			'options_page'
		), $image);
		$page_settings = add_submenu_page( 'pba-importer_settings', __( 'PBA Importer', 'pba-importer' ) . __( ' Configurações', 'pba-importer' ), __( 'Configurações', 'pba-importer' ), 'manage_options', 'pba-importer_settings', array(
			&$this,
			'options_page'
		) );
		$page_colorbox = add_submenu_page( 'pba-importer_settings', __( 'PBA Importer', 'pba-importer' ) . __( ' Planos', 'pba-importer' ), __( 'Planos', 'pba-importer' ), 'manage_options', 'pba-importer_planos', array(
			&$this,
			'handle_planos'
		) );


	}
	
		// Handle our options
	function get_options() {
		$options = array(
			'shop_url' 		=> 'http://hostname.domain.com/shop/pt/',
			'hspc_url' 		=> 'http://hostname.domain.com/hspc/xml-api',
			'hspc_login' 	=> 'root',
			'hspc_pass' 	=> ''
		);
		
		$saved = get_option( $this->key );
		
		if ( ! empty( $saved ) ) {
			foreach ( $saved as $key => $option ) {
				$options[$key] = $option;
			}
		}
			  
		if ( $saved != $options ) {
			update_option( $this->key, $options );
		}
		
		$GLOBALS['StoreConf']['HSPCOMPLETE_SERVER'] = $options['hspc_url'];
		$GLOBALS['StoreConf']['SERVER_NAME'] 		= 'hostgenio.com.br';//$options['hspc_url'];
		$GLOBALS['StoreConf']['VENDOR_EMAIL'] 		= $options['hspc_login'];
		$GLOBALS['StoreConf']['VENDOR_PASSWORD'] 	= $options['hspc_pass'];
		
		return $options;
	}
	
	function options_page() { 
		// If form was submitted
		if ( isset( $_POST['submitted'] ) ) {			
			check_admin_referer( 'pba-importer' );
			
			$this->options['shop_url'] = ! isset( $_POST['shop_url'] ) ? '' : $_POST['shop_url'];
			$this->options['hspc_url'] = ! isset( $_POST['hspc_url'] ) ? '' : $_POST['hspc_url'];
			$this->options['hspc_login'] = ! isset( $_POST['hspc_login'] ) ? '' : $_POST['hspc_login'];
			$this->options['hspc_pass'] = ! isset( $_POST['hspc_pass'] ) ? '' : $_POST['hspc_pass'];
			
			update_option( $this->key, $this->options );
			
			// Show message
			echo '<div id="message" class="updated fade"><p>' . __( 'Configurações atualizadas', 'pba-importer' ) . '</p></div>';
		} 
		
		$shop_url = $this->options['shop_url'];
		$hspc_url = $this->options['hspc_url'];
		$hspc_login = $this->options['hspc_login'];
		$hspc_pass = $this->options['hspc_pass'];
		
		global $wp_version;
		
		$imgpath = $this->plugin_url.'/i';
		$actionurl = stripslashes(htmlentities(strip_tags($_SERVER['REQUEST_URI'])));
		$nonce = wp_create_nonce( 'pba-importer' );
		
		// Configuration Page
		
?>
<div class="wrap" >
	<?php screen_icon(); ?>
	<h2><?php _e( 'PBA Importer', 'pba-importer' ); echo ' - '; _e( 'Configurações', 'pba-importer' ); ?></h2>
	<a href="admin.php?page=pba-importer_settings"><?php _e( 'Configurações', 'pba-importer' ); ?></a> &nbsp;|&nbsp; <a href="admin.php?page=pba-importer_planos"><?php _e( 'Planos', 'pba-importer' ); ?></a>
	<div id="poststuff" style="margin-top:10px;">
		<div class="dbx-content">
			<form name="pba-importer_form" action="<?php echo $actionurl; ?>" method="post">
				<input type="hidden" name="submitted" value="1" /> 
				<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo $nonce; ?>" />
				
				<h2>Dados para integração com a XML Api</h2>
				<table cellpadding="2" cellspacing="0">
					<tr>
						<td><label for="shop_url"><?php _e( 'Shop URL:', 'pba-importer' ); ?></label></td>
						<td><input id="shop_url" type="text" name="shop_url" value="<?php echo $shop_url; ?>" style="width: 300px;" /></td>
					</tr>
					<tr>
						<td><label for="hspc_url"><?php _e( 'URL:', 'pba-importer' ); ?></label></td>
						<td><input id="hspc_url" type="text" name="hspc_url" value="<?php echo $hspc_url; ?>" style="width: 300px;" /></td>
					</tr>
					<tr>
						<td><label for="hspc_login"><?php _e( 'Login:', 'pba-importer' ); ?></label></td>
						<td><input id="hspc_login" type="text" name="hspc_login" value="<?php echo $hspc_login; ?>" style="width: 300px;" /></td>
					</tr>
					<tr>
						<td><label for="hspc_pass"><?php _e( 'Senha:', 'pba-importer' ); ?></label></td>
						<td><input id="hspc_pass" type="password" name="hspc_pass" value="<?php echo $hspc_pass; ?>" style="width: 300px;" /></td>
					</tr>
				</table>
				
				<div class="submit"><input type="submit" name="Submit" value="<?php _e( 'Salvar', 'pba-importer' ); ?>" /></div>
			</form>
		</div>
	</div>
</div>
<?php
	}
	
	function handle_planos() { 
	
		// If form was submitted
		if ( isset( $_POST['submitted'] ) ) {			
			check_admin_referer( 'pba-importer' );
			
			if($_POST['action'] == 'update') {

				$hspc_url = $this->options['hspc_url'];
				$hspc_login = $this->options['hspc_login'];
				$hspc_pass = $this->options['hspc_pass'];

				$HspcApi = new HspcApi($hspc_url, $hspc_login, $hspc_pass);
				$planos = $HspcApi->get_planos();
				
				$this->update_planos($planos);
				
				// Show message
				echo '<div id="message" class="updated fade"><p>' . __( 'Planos atualizados', 'pba-importer' ) . '</p></div>';

			} else {

				// Show message
				//echo '<div id="message" class="updated fade"><p>' . __( 'Configurações atualizadas', 'pba-importer' ) . '</p></div>';
				
			}
						
		} 
		
		global $wp_version;
		
		$imgpath = $this->plugin_url.'/i';
		$actionurl = stripslashes(htmlentities(strip_tags($_SERVER['REQUEST_URI'])));
		$nonce = wp_create_nonce( 'pba-importer' );
		
		
		echo '<div class="wrap">';
			screen_icon();
			echo '<h2>'; _e( 'PBA Importer', 'pba-importer' ); echo ' - '; _e( 'Planos', 'pba-importer' ); echo '</h2>';
			echo '<a href="admin.php?page=pba-importer_settings">'; _e( 'Configurações', 'pba-importer' ); echo '</a> &nbsp;|&nbsp; <a href="admin.php?page=pba-importer_planos">'; _e( 'Planos', 'pba-importer' );echo '</a>';
			echo '<div id="poststuff" style="margin-top:10px;">';
				echo '<div class="dbx-content">';
				
					echo '<form name="pba-importer_form" action="'. $actionurl.'" method="post">';
						echo '<input type="hidden" name="submitted" value="1" /> ';
						echo '<input type="hidden" id="_wpnonce" name="_wpnonce" value="'. $nonce.'" />';
						echo '<input type="hidden" name="action" value="update" /> ';
						echo '<div class="submit"><input type="submit" name="Submit" value="'; _e( 'Atualizar informações', 'pba-importer' ); echo '" /></div>';
					echo '</form>';

					echo $this->montaHtmlPlanos();
				
				echo '</div>';
			echo '</div>';
		echo '</div>';

	}
	
	
	
	function update_planos($planos) {
		global $wpdb;
		
		//Exclui planos atuais
		$wpdb->query('DELETE FROM '.$wpdb->prefix.'pbaimporter_planos');
		
		foreach ($planos as $plano) {
			
			$sql = '
				INSERT INTO '.$wpdb->prefix.'pbaimporter_planos 
				(
					`series_key`,
					`period`,
					`nome`,
					`descricao`,
					`summary`,
					`valor_anual`,
					`valor_mensal`
				)
				VALUES
				(
					\''.$plano['series_key'].'\',
					\''.$plano['period'].'\',
					\''.$plano['name'].'\',
					\''.$plano['description'].'\',
					\''.$plano['summary'].'\',
					\''.$plano['preco_anual'].'\',
					\''.$plano['preco_mensal'].'\'
				)';
/*
			echo '<pre>';
			echo $sql;
			echo '</pre>';
*/
			
			$wpdb->query($sql);
		}
		
	}
	
	
	
	
	
	



	/**
    * Looks for PBA-IMPORTER TAG
    * and replace them with proper HTML tags
    *
    * @param mixed $the_content
    * @param mixed $side
    * @return mixed
    */
	function check($the_content, $side = 0)
	{
    	$the_content = str_replace('[pba-importer]', $this->montaHtmlPlanos(), $the_content);
    	$the_content = str_replace('[pba-importer-planos]', $this->montaHtmlPlanos(), $the_content);
    	$the_content = str_replace('[pba-importer-check-domain]', $this->checkDomain(), $the_content);

        return $the_content;
	}
	
	function checkDomain() {
		$_SESSION['account']['account_id'] = null;
		
		$ret = '';
		
		$dominio = '';
		
		if(isset($_GET['domain'])) {
			$dominio = $_GET['domain'] . '' . (isset($_GET['tld']) ? $_GET['tld'] : 'com.br');
		}
		if(isset($_POST['domain'])) {
			$dominio = $_POST['domain'] . '' . (isset($_POST['tld']) ? $_POST['tld'] : 'com.br');
		}
		
		if($dominio != '') {
		
			$domain_names = array($dominio);
			$series_key = 1;
			$dm_action = 'register_new';
			$check_error_info = true;
			$obj = null;
			
			$a = check_domains($domain_names, $series_key, $dm_action, $check_error_info, $obj);
			
			if( count($a['available_domain_list']) > 0 ) {
				
				$ret .= '<h2>O domínio <strong>'.$dominio.'</strong> está disponível.</h2>';
				$ret .= '<p>Escolha um de nossos planos de hospedagem</p>';
				
			} else {
				
				$ret .= '<h2>Infelizmente o domínio <strong>'.$dominio.'</strong> não está disponível</h2>';
				
			}
		}
	
		return $ret;		
	}

	function montaHtmlPlanos($row_class = 'row-fluid plans', $index_destaque = false)
	{
		global $wpdb;

		//get data
		
		$result = $wpdb->get_results('
			SELECT *
			FROM '.$wpdb->prefix.'pbaimporter_planos
			ORDER BY valor_mensal asc
		');


/* 		var_dump($result); */
		
		$planos = array();
		
		$i = 0;
		foreach($result as $item) {
			
			$class_item = 'item_table';
			if ($i === $index_destaque) {
				$class_item .= ' promotion_table';
			}
			$i++;
			
			$a = '
			<div class="span4">
				<div class="'.$class_item.'">
		            <div class="head_table">
		                <span class="arrow_table"></span>
		                <h1>' . $item->nome  .'</h1>
		                <h2>' . number_format($item->valor_mensal, '2', ',', '.') . '<span> / mês*</span></h2>
		                <h5>Ou ' . number_format($item->valor_anual, '2', ',', '.') . ' por ano!</h5>
		            </div>
		            <a class="button btselplano" data-plan_id="'.$item->series_key.'" data-period="'.$item->period.'" href="#">Desejo Este!</a>
		            <ul>';
			$aux = explode("\n", $item->descricao);
			$class = 'color';
			foreach($aux as $aux2) {
				if($aux2 != '') {
					$a .= '<li class="'.$class.'">'.$aux2.'</li>';
					
					$class = $class == 'color' ? '' : 'color';
				}
			}		                
			$a .= ' 
					</ul>
		        </div>
	        </div>';
	        
	        $planos[] = $a;
		}

		$ret = '';
		//Divide em 3 por linha
		$qtd = ceil(count($planos) / 3);
		for($i = 0; $i < $qtd; $i++)
		{ 
			$ret .= '<div class="'.$row_class.'">';
				$ret .= implode('', array_slice($planos, $i * 3, 3));
			$ret .= '</div>';
		}

		$domain = (isset($_GET['domain']) ? $_GET['domain'] : isset($_POST['domain']) ? $_POST['domain'] : '');
		$tld    = (isset($_GET['tld'])    ? $_GET['tld']    : isset($_POST['tld'])    ? $_POST['tld']    : 'com.br');
		if(substr($tld, 0, 1) == '.') {
			$tld = substr($tld, 1);
		}

		$ret .= '<input type="hidden" id="pba_domain" value="'.$domain.'" />';
		$ret .= '<input type="hidden" id="pba_tld"    value="'.$tld.'" />';
		$ret .= '<div id="res" style="display:none;"></div>';
		
		$ret .= '<p class="center">Escolha a opção mais adequada para você!<br>* Valores para contratação de 3 anos.</p>';

		//script
		$script = '
		<script type="text/javascript">
		<!--
			
			var plan_id_sel = \'\';
			var period_sel = \'\';
				
			jQuery(\'.btselplano\').click(function(e) {
			
				plan_id_sel = jQuery(this).data(\'plan_id\');
				period_sel = jQuery(this).data(\'period\');

				seleciona_plano(plan_id_sel, period_sel);
				
				e.preventDefault();
			});
			
			function seleciona_plano(id, period) {

				jQuery.ajax({
					dataType: \'jsonp\',
					url: \''.$this->options['shop_url'].'updateshoppingcart/\'+id+\'/_plans_\'+id+\'/undefined/\'+period+\'/undefined\',
					jsonp: \'void\',
					complete: function(){ 
						seleciona_plano_complete(); 
					}
				});
				
			}
			
			function seleciona_plano_complete() {
			
				var domain = jQuery(\'#pba_domain\').val();
				
				if (domain != \'\') {
					seleciona_dominio();
				} else {
					window.location = \''.$this->options['shop_url'].'\';
				}
						
			}
			
			function seleciona_dominio() {

				jQuery.post(
					\'' . $this->options['shop_url'] . 'domains\',
					{
						action: 				\'check_domains\',
						series_key: 			\'1\',
						plan_id: 				plan_id_sel,
						dm_action: 				\'register_new\',
						domain_selection_type: 	\'single\',
						domain_name: 			jQuery(\'#pba_domain\').val(),
						\'tld[]\': 				jQuery(\'#pba_tld\').val()
					},
					function (data) {
						
						if( data.result == \'success\') {
						
							jQuery(\'#res\').html(\'<iframe></iframe>\');
							jQuery(\'#res iframe\').contents().find(\'body\').html(data.html);
							
							jQuery(\'#res iframe\').ready(function(){
								window.location = \''.$this->options['shop_url'].'\';
							});
							
							jQuery(\'#res iframe\').contents().find(\'form\').attr(\'action\', \'' . $this->options['shop_url'] . 'domains\').submit();

						} else {
							alert(\'Erro ao selecionar dominio\')
						}
						
					}
				);
				
			}
		
		//-->
		</script>
		';

		

        return $ret . $script;
	}

    function install()
    {
    	global $wpdb;

		//Criar base de dados
		$sql1 = "
		CREATE  TABLE IF NOT EXISTS `".$wpdb->prefix."pbaimporter_planos` (
			`id` INT NOT NULL AUTO_INCREMENT,
			`series_key` INT NOT NULL,
			`period` varchar(30) NULL,
			`nome` varchar(100) not NULL ,
			`descricao` text NULL ,
			`summary` text NULL ,
			`valor_anual` decimal(10,2) default 0 ,
			`valor_mensal` decimal(10,2) default 0 ,
			PRIMARY KEY (`id`) 
		)";

		$wpdb->query($sql1);
    }


}
?>
