<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class designImgSwitch extends eqLogic {

    public static function pullRefresh($_option) {
        log::add(__CLASS__, 'debug', 'pullRefresh started');

        $eqLogic = self::byId($_option['id']);
		if (is_object($eqLogic) && $eqLogic->getIsEnable() == 1) {
			$eqLogic->refreshPlanHeaderBackground();
        }
    }


    /*     * *********************Méthodes d'instance************************* */

    private function getListener() {
        return listener::byClassAndFunction(__CLASS__, 'pullRefresh', array('id' => $this->getId()));
    }

    private function removeListener() {
        $listener = $this->getListener();
        if (is_object($listener)) {
            $listener->remove();
        }
    }

    private function setListener() {
        if ($this->getIsEnable() == 0) {
            $this->removeListener();
            return;
        }

        $this->checkConfigurationAndGetCommands($cmd_condition, $cmd_sunrise, $cmd_sunset);

        $listener = $this->getListener();
        if (!is_object($listener)) {
            $listener = new listener();
            $listener->setClass(__CLASS__);
            $listener->setFunction('pullRefresh');
            $listener->setOption(array('id' => $this->getId()));
        }
        $listener->emptyEvent();
        $listener->addEvent($cmd_condition->getId());
        $listener->addEvent($cmd_sunrise->getId());
        $listener->addEvent($cmd_sunset->getId());
        $listener->save();

        $this->refreshPlanHeaderBackground();
    }

    public function preInsert() {

    }

    public function postInsert() {

    }

    public function preSave() {

    }

    public function postSave() {
        $cmd = $this->getCmd(null, 'refresh');
        if (!is_object($cmd)) {
            $cmd = new designImgSwitchCmd();
            $cmd->setLogicalId('refresh');
            $cmd->setIsVisible(1);
            $cmd->setName('Rafraichir');
            $cmd->setType('action');
            $cmd->setSubType('other');
            $cmd->setEqLogic_id($this->getId());
            $cmd->save();
        }
    }

    public function preUpdate() {
        if ($this->getIsEnable() == 0) {
            return;
        }
        $this->checkConfigurationAndGetCommands();
    }

    public function postUpdate() {
        $this->setListener();
    }

    public function preRemove() {

    }

    public function postRemove() {
        $this->removeListener();
    }

    private function checkConfigurationAndGetCommands(&$cmd_condition = null, &$cmd_sunrise=null, &$cmd_sunset=null) {
        $weatherEqLogicId = $this->getConfiguration('weatherEqLogic');
        if ($weatherEqLogicId == '') {
            throw new Exception(__("Veuillez configurer l'équipement météo à utiliser", __FILE__));
        }
        $cmd_condition = cmd::byEqLogicIdAndLogicalId($weatherEqLogicId, 'condition_id');
        if (!is_object($cmd_condition)) {
            throw new Exception(__("La commande 'Numéro condition' (condition_id) de l'équipement Météo (weather) est introuvable, veuillez vérifier la configuration." , __FILE__));
        }
        $cmd_sunrise = cmd::byEqLogicIdAndLogicalId($weatherEqLogicId, 'sunrise');
        if (!is_object($cmd_sunrise)) {
            throw new Exception(__("La commande 'Lever du soleil' (sunrise) de l'équipement Météo (weather) est introuvable, veuillez vérifier la configuration." , __FILE__));
        }
        $cmd_sunset = cmd::byEqLogicIdAndLogicalId($weatherEqLogicId, 'sunset');
        if (!is_object($cmd_sunset)) {
            throw new Exception(__("La commande 'Coucher du soleil' (sunset) de l'équipement Météo (weather) est introuvable, veuillez vérifier la configuration." , __FILE__));
        }
    }

    private function getPlanHeaders() {
        $planHeader = array();
        foreach ($this->getConfiguration('planHeader') as $planHeaderId => $isActive) {
            if ($isActive == 1) {
                $planHeader[] = $planHeaderId;
            }
        }
        return $planHeader;
    }

    public function refreshPlanHeaderBackground() {
        $this->checkConfigurationAndGetCommands($cmd_condition, $cmd_sunrise, $cmd_sunset);

        $planHeaders = $this->getPlanHeaders();
        if (!is_array($planHeaders) || count($planHeaders)==0) {
            log::add(__CLASS__, 'info', __("Aucun design sélectionné." , __FILE__));
            return;
        }

        $condition = $cmd_condition->execCmd();
        $sunrise = $cmd_sunrise->execCmd();
        $sunset = $cmd_sunset->execCmd();

        $heure = date('Hi');
        if ($heure>=$sunrise && $heure < $sunset) {
            $moment = "jour";
        } else {
            $moment = "nuit";
        }
        log::add(__CLASS__, 'debug', "jour / nuit ? : {$moment}");

        $numGroup = substr($condition, 0,1);
        log::add(__CLASS__, 'debug', "condition : {$condition}");

        switch ($numGroup) {
            case '2':
                $valeur_condition = "Orage";
                break;
            case '3':
                $valeur_condition = "Brume";
                break;
            case '5':
                $valeur_condition = "Pluie";
                break;
            case '6':
                $valeur_condition = "Neige";
                break;
              case '7':
                $valeur_condition = "Brume";
                break;
            case '8':
                $valeur_condition = "Nuage";
                break;
            default:
                $valeur_condition = "defaut";
                break;
        }

        //Conditions particulières
        if(in_array($condition, array('781', '905', '902', '900', '952', '953', '954', '955', '956', '957', '960', '961'))){
            $valeur_condition = "Vent";
        } else if(in_array($condition, array('800', '951'))){
            $valeur_condition = "Soleil";
        } else if($condition == '909'){
            $valeur_condition = "Pluie";
        }

        log::add(__CLASS__, 'debug', "valeur_condition : {$valeur_condition}");
        $file = realpath(__DIR__ . "/../images/{$moment}/{$valeur_condition}.jpg");
        log::add(__CLASS__, 'debug', "file : {$file}");

        $img_size = getimagesize($file);
        $data = base64_encode(file_get_contents($file));
        $sha512 = sha512($data);
        $type = 'jpg';
        foreach($planHeaders as $planId) {
            log::add(__CLASS__, 'info', sprintf(__("Mise à jour de l'image de fond du design %s avec %s.jpg" , __FILE__), $planId, $moment . '/' . $valeur_condition));
            $planHeader = planHeader::byId($planId);
            $planHeader->setImage('type', $type);
            $planHeader->setImage('size', $img_size);
            $planHeader->setImage('sha512', $sha512);

            $planfilename = 'planHeader'.$planId.'-'.$sha512.'.'.$type;
            $planfilepath = __DIR__ . '/../../../../data/plan/' . $planfilename;
            log::add(__CLASS__, 'debug', "planfilepath : {$planfilepath}");
            file_put_contents($planfilepath,file_get_contents($file));
            $planHeader->save();
        }

        $gotoDesignId = $this->getConfiguration('gotoDesign', '');
        if ($gotoDesignId != '') {
            log::add(__CLASS__, 'info', __('Changement design : ', __FILE__) . $gotoDesignId);
            event::add('jeedom::gotoplan', $gotoDesignId);
        }
    }
}

class designImgSwitchCmd extends cmd {
    public function dontRemoveCmd() {
        return true;
    }

    public function execute($_options = array()) {
        if($this->getLogicalId()=='refresh') {
            $this->getEqLogic()->refreshPlanHeaderBackground();
        }
    }
}
