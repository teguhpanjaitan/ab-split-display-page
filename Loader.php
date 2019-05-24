<?php
class Loader{
     private $pluginDir;
     
     public function __construct($pluginDir){
          $this->pluginDir = $pluginDir . "split-page-in-one/";

          //load composer
          require __DIR__ . '/vendor/autoload.php';
     }

     public function __invoke(){
     }

     public function executeShortcode($atts){
          $detect = new Mobile_Detect;
          
          if ( $detect->isMobile() ) {
               if(isset($atts['mobile'])){
                   return $this->getContent($atts['mobile']);
               }
          }
          else if( $detect->isTablet() ){
               if(isset($atts['mobile'])){
                    return $this->getContent($atts['mobile']);
               }
          }
          else{
               if(isset($atts['desktop'])){
                    return $this->getContent($atts['desktop']);
               }
          }
     }

     private function getContent($id){
          $post   = get_post( intval($id) );
          $output =  apply_filters( 'the_content', $post->post_content );
          $output = str_replace(']]>', ']]>', $output);
          return $output;
     }
}