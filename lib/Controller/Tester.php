<?php // vim:ts=4:sw=4:et:fdm=marker
/*
 * Undocumented
 *
 * @link http://agiletoolkit.org/
*//*
==ATK4===================================================
   This file is part of Agile Toolkit 4
    http://agiletoolkit.org/

   (c) 2008-2013 Agile Toolkit Limited <info@agiletoolkit.org>
   Distributed under Affero General Public License v3 and
   commercial license.

   See LICENSE or LICENSE_COM for more information
 =====================================================ATK4=*/
/** Tester page is a basic implementation of a testing environment for Agile Toolkit.
    See Documentation for testing */
class Controller_Tester extends Page {
    public $variances=array();
    public $input;
    public $responses=array();
    public $auto_test=true;

    /** Redefine this to the value generated by a test */
    public $proper_responses=null;

    function setVariance($arr){
        $this->variances=$arr;
        if(isset($this->grid)){
            foreach($arr as $key=>$item){
                if(is_numeric($key))$key=$item;
                $this->grid->addColumn('html',$key.'_inf',$key.' info');
                $this->grid->addColumn('html,wrap',$key.'_res',$key.' result');
            }
        }
    }
    function init(){
        parent::init();


        if(!$this->auto_test){
            $this->setVariance(array('Test'));
            return;    // used for multi-page testing
        }
        //$this->grid=$this->add('Grid');
        $this->grid->addColumn('template','name')->setTemplate('<a href="'.$this->api->url(null,array('testonly'=>'')).'<?$name?>"><?$name?></a>');

        $this->setVariance(array('Test'));

        //$this->setVariance(array('GiTemplate','SMlite'));


        //$this->runTests();

    }
    function skipTests($msg=null){
        throw $this->exception($msg,'SkipTests');
    }
    public $cnt;
    function ticker(){
        $this->cnt++;
    }
    function executeTest($test_obj,$test_func,$input){
        if($input === null)$input=array();
        return call_user_func_array(array($test_obj,$test_func),$input);
    }
    function silentTest($test_obj=null){
        if(!$test_obj)$test_obj=$this;

        $total=$success=$fail=$exception=0;
        $speed=$memory=0;

        $tested=array();
        $failures=array();
        foreach(get_class_methods($test_obj) as $method){
            if(strpos($method,'test_')===0){
                $m=substr($method,5);
            }elseif(strpos($method,'prepare_')===0){
                $m=substr($method,8);
            }else continue;
            if($tested[$m])continue;$tested[$m]=true;

            foreach($this->variances as $key=>$vari){
                if(is_numeric($key))$key=$vari;

                // Input is a result of preparation function
                try{
                    if(method_exists($test_obj,'prepare_'.$m)){
                        $input=$test_obj->{'prepare_'.$m}($vari,$method);
                    }else{
                        if(($test_obj instanceof \AbstarctObject && $test_obj->hasMethod('prepare')) || method_exists($test_obj, 'prepare')){
                            $input=$test_obj->prepare($vari,$method);
                        }else $input=null;
                    }
                }catch (Exception $e){

                    if($e instanceof Exception_SkipTests) {
                        return array(
                            'skipped'=>$e->getMessage()
                        );
                    }
                    throw $e;
                }

                $this->input=$input;

                $test_func=method_exists($test_obj,'test_'.$m)?
                    'test_'.$m:'test';

                $total++;

                $me=memory_get_peak_usage();
                $ms=microtime(true);


                $this->cnt=0;
                declare(ticks=1);
                register_tick_function(array($this,'ticker'));

                try{
                    $result=$this->executeTest($test_obj,$test_func,$input);
                    $ms=microtime(true)-$ms;
                    $me=($mend=memory_get_peak_usage())-$me;

                    $result=$this->formatResult($row,$key,$result);

                    $k=$key.'_'.$m;
                    if($this->proper_responses[$k]==$result && isset($this->proper_responses[$k])){
                        $success++;
                    }else{
                        $failures[]=$method;
                        $fail++;
                    }
                }catch (Exception $e){

                    if($e instanceof Exception_SkipTests) {
                        return array(
                            'skipped'=>$e->getMessage()
                        );
                    }

                    $exception++;

                    $ms=microtime(true)-$ms;
                    $me=($mend=memory_get_peak_usage())-$me;
                }

                unregister_tick_function(array($this,'ticker'));

                $speed+=$this->cnt*1;
                $memory+=$me;
            }
        }
        return array(
            'total'=>$total,
            'failures'=>$failures,
            'success'=>$success,
            'exception'=>$exception,
            'fail'=>$fail,
            'speed'=>$speed,
            'memory'=>$memory
            );
    }
    function runTests($test_obj=null){

        if(!$test_obj){
            $test_obj=$this;
        }else{
            $this->proper_responses = @$test_obj->proper_responses;

        }

        $tested=array();
        $data=array();
        foreach(get_class_methods($test_obj) as $method){
            $m='';
            if(strpos($method,'test_')===0){
                $m=substr($method,5);
            }elseif(strpos($method,'prepare_')===0){
                $m=substr($method,8);
            }else continue;

            if(isset($_GET['testonly']) && 'test_'.$_GET['testonly']!=$method)continue;

            // Do not retest same function even if it has both prepare and test
            if($tested[$m])continue;$tested[$m]=true;

            // Row contains test result data
            $row=array('name'=>$m,'id'=>$m);

            foreach($this->variances as $key=>$vari){
                if(is_numeric($key))$key=$vari;

                try{
                    // Input is a result of preparation function
                    if(method_exists($test_obj,'prepare_'.$m)){
                        $input=$test_obj->{'prepare_'.$m}($vari,$method);
                    }else{
                        if(($test_obj instanceof \AbstarctObject && $test_obj->hasMethod('prepare')) || method_exists($test_obj, 'prepare')){
                            $input=$test_obj->prepare($vari,$method);
                        }else $input=null;
                    }
                }catch (Exception $e){
                    if($e instanceof Exception_SkipTests) {
                        $this->grid->destroy();
                        $this->add('View_Error')->set('Skipping all tests: '.$e->getMessage());
                        return;
                    }
                }

                $this->input=$input;

                $test_func=method_exists($test_obj,'test_'.$m)?
                    'test_'.$m:'test';

                // Test speed
                $me=memory_get_peak_usage();
                $ms=microtime(true);
                $this->cnt=0;
                declare(ticks=1);
                register_tick_function(array($this,'ticker'));
                try{
                    //$result=$test_obj->$test_func($input[0],$input[1],$input[2]);
                    $result=$this->executeTest($test_obj,$test_func,$input);
                }catch (Exception $e){

                    if($e instanceof Exception_SkipTests) {
                        $this->grid->destroy();
                        $this->add('View_Error')->set('Skipping all tests: '.$e->getMessage());
                    }


                    if($_GET['tester_details']==$row['name'] && $_GET['vari']==$vari){
                        throw $e;
                    }


                    $result='Exception: '.(method_exists($e,'getText')?
                        $e->getText():
                        $e->getMessage());

                    $ll=$this->add('P',$row['name']);
                    $v=$ll->add('View')
                        ->setElement('a')
                        ->setAttr('href','#')
                        ->set('More details')
                        ->js('click')->univ()->frameURL('Exception Details for test '.$row['name'],
                        $this->api->url(null,array('tester_details'=>$row['name'],'vari'=>$vari)))
                        ;

                    $result.=$ll->getHTML();
                }
                $ms=microtime(true)-$ms;
                $me=($mend=memory_get_peak_usage())-$me;
                unregister_tick_function(array($this,'ticker'));

                $row[$key.'_inf']='Ticks: '.($this->cnt*1).'<br/>Memory: '.$me;

                $result=$this->formatResult($row,$key,$result);

                $k=$key.'_'.$row['name'];
                if($this->proper_responses[$k]==$result && isset($this->proper_responses[$k])){
                    $row[$key.'_res']='<font color="green">PASS</font><br/>'.htmlspecialchars($result);
                }elseif($this->proper_responses[$k]){
                    $row[$key.'_res']='<font color="red">'.htmlspecialchars($result).'</font><br/>'.
                        var_export($this->proper_responses[$k],true);
                }


                $this->responses[]='"'.$k.'"'.'=>'.var_export($result,true);
            }

            $data[]=$row;
        }
        $this->grid->setSource($data);
        if(!$_GET['testonly']){
            $f=$this->form;
            $ff=$f->addField('Text','responses');
            $this->responses=
    '    public $proper_responses=array(
        '.join(',
        ',$this->responses).'
    );';
            $ff->set($this->responses);
            $ff->js('click')->select();
        }
    }
    function formatResult(&$row,$key,$result){
        $row[$key.'_res']=$result;
        return (string)$result;
    }
    function expect($value,$expectation){
        return $value==$expectation?'OK':'ERR';
    }

    function _prepare($t,$str){
        $result='';

        for($i=0;$i<100;$i++){
            $result.=$str;
        }
        return array($this->add($t),$result);
    }

}
