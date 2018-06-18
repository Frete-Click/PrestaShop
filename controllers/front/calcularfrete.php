<?php
/**
 *  Módulo para o calculo do frete usando o webservice do FreteClick
 *  @author    Ederson Ferreira (ederson.dev@gmail.com)
 *  @copyright 2010-2015 FreteClick
 *  @license   LICENSE
 */

class FreteclickCalcularfreteModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
		echo $this->module->quote($_POST);
		exit;
    }
}
