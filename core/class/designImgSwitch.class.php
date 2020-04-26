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

        $this->checkConfigurationAndGetCommands($cmd_nuit);

        $listener = $this->getListener();
        if (!is_object($listener)) {
            $listener = new listener();
            $listener->setClass(__CLASS__);
            $listener->setFunction('pullRefresh');
            $listener->setOption(array('id' => $this->getId()));
        }
        $listener->emptyEvent();
        $listener->addEvent($cmd_nuit->getId());
        $listener->save();

        $this->refreshPlanHeaderBackground();
    }

    public function preInsert() {
        $this->setConfiguration('cropImage', 1);
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
        $this->removeListener();
    }

    public function postRemove() {

    }

    private function checkConfigurationAndGetCommands(&$cmd_nuit = null) {
        $cmd_nuit = cmd::byId(2084);
        if (!is_object($cmd_nuit)) {
            throw new Exception(__("La commande 'Nuit' est introuvable, veuillez vérifier le code." , __FILE__));
        }
    }

    private function getPlanHeaders() {
        $planHeaders = array();
        foreach ($this->getConfiguration('planHeader') as $planHeaderId => $isActive) {
            if ($isActive == 1) {
                $planHeaders[] = $planHeaderId;
            }
        }
        return $planHeaders;
    }

    public static function getPicturePath($object, $condition) {
        if (file_exists(__DIR__ . "/../pictures/custom/{$object}_{$condition}.png")) {
            return "pictures/custom/{$object}_{$condition}.png";
        }
        return "pictures/default/day-default.jpg";
    }

    private static function SaveImagePlanHeader($planHeader, $sourceImage) {
        $img_size = getimagesize($sourceImage);
        $data = base64_encode(file_get_contents($sourceImage));
        $sha512 = sha512($data);
        $extension = strtolower(strrchr($sourceImage, '.'));

        $planHeader->setImage('type', str_replace('.', '', $extension));
        $planHeader->setImage('size', $img_size);
        $planHeader->setImage('sha512', $sha512);
        $planHeader->save();

        $planfilename = 'planHeader'.$planHeader->getId().'-'.$sha512.$extension;
        $planfilepath = __DIR__ . '/../../../../data/plan/' . $planfilename;
        copy($sourceImage, $planfilepath);
    }

    private function AdaptAndSaveImgForPlan($sourceFile, $planId) {
        $planHeader = planHeader::byId($planId);
        log::add(__CLASS__, 'info', sprintf(__("Mise à jour de l'image du design %s-%s avec %s" , __FILE__), $planId, $planHeader->getName(), $sourceFile));

        /*if ($this->getConfiguration('cropImage', 1)==0) {*/
            log::add(__CLASS__, 'debug', "no crop, copy image");
            designImgSwitch::SaveImagePlanHeader($planHeader, $sourceFile);
            return;
        /*}*/
        /*
        $img_size = getimagesize($sourceFile);
        $imgWidth = $img_size[0];
        $imgHeight = $img_size[1];
        unset($img_size);
        $planWidth = $planHeader->getConfiguration('desktopSizeX');
        $planHeight = $planHeader->getConfiguration('desktopSizeY');
        log::add(__CLASS__, 'debug', "image: {$imgWidth}/{$imgHeight} - plan:{$planWidth}/{$planHeight}");

        $ratioWidth = $imgWidth/$planWidth;
        $ratioheight = $imgHeight/$planHeight;
        if ($ratioWidth == $ratioheight) {
            log::add(__CLASS__, 'debug', "ratio is the same, copy image");
            designImgSwitch::SaveImagePlanHeader($planHeader, $sourceFile);
            return;
        }

        log::add(__CLASS__, 'debug', "crop image");
        $extension = strtolower(strrchr($sourceFile, '.'));
        $type = str_replace('.', '', $extension);
        switch ($type) {
            case 'jpg':
                $imagecreatefromFunction = 'imagecreatefromjpeg';
                $imageFunction = 'imagejpeg';
                break;
            case 'png':
                $imagecreatefromFunction = 'imagecreatefrompng';
                $imageFunction = 'imagepng';
                break;
            default:
                throw new Exception('Unusupported image type');
        }

        $diffWidth = $imgWidth-$planWidth;
        $diffHeight = $imgHeight-$planHeight;
        log::add(__CLASS__, 'debug', "diffWidth:{$diffWidth} - diffHeight:{$diffHeight}");
        if ($diffHeight>$diffWidth) {
            $x = 0;
            $newImgWith = $imgWidth;
            $newImgHeight = $planHeight * $ratioWidth;
            $y = ($imgHeight - $newImgHeight) / 2;
            log::add(__CLASS__, 'debug', "keep width, newImgHeight:{$newImgHeight}");
        } else {
            $newImgWith = $planWidth * $ratioheight;
            $x = ($imgWidth - $newImgWith) / 2;
            $y = 0;
            $newImgHeight = $imgHeight;
            log::add(__CLASS__, 'debug', "keep height, newImgWith:{$newImgWith}");
        }

        $srcImg = $imagecreatefromFunction($sourceFile);
        $destImg = imagecrop($srcImg, ['x' => $x, 'y' => $y, 'width' => $newImgWith, 'height' => $newImgHeight]);
        if ($destImg !== FALSE) {
            $tmpFile = jeedom::getTmpFolder(__CLASS__) . '/' . mt_rand() . $extension;
            log::add(__CLASS__, 'debug', "crop: {$newImgWith}/{$newImgHeight} - {$x}/{$y} - output:{$tmpFile}");
            $imageFunction($destImg, $tmpFile);
            imagedestroy($destImg);
            designImgSwitch::SaveImagePlanHeader($planHeader, $tmpFile);
            unlink($tmpFile);
        }
        imagedestroy($srcImg);
        */
    }

    public function refreshPlanHeaderBackground() {
        $this->checkConfigurationAndGetCommands($cmd_nuit);

        $planHeaders = $this->getPlanHeaders();
        if (!is_array($planHeaders) || count($planHeaders)==0) {
            log::add(__CLASS__, 'info', __("Aucun design sélectionné." , __FILE__));
            return;
        }

        $picturePath = realpath(__DIR__ . '/../' . designImgSwitch::getPicturePath('home', $cmd_nuit));
        log::add(__CLASS__, 'debug', "picturePath : {$picturePath}");

        foreach($planHeaders as $planId) {
            log::add(__CLASS__, 'info', sprintf(__('Suppression des images précédentes pour le design %s' , __FILE__), $planId));
            $oldFiles = ls(__DIR__ . '/../../../../data/plan/','planHeader'.$planId.'*');
            if(count($oldFiles)  > 0){
                foreach ($oldFiles as $oldFile) {
                    unlink(__DIR__ . '/../../../../data/plan/'.$oldFile);
                }
            }

            $this->AdaptAndSaveImgForPlan($picturePath, $planId);
        }
        /*
        $gotoDesignId = $this->getConfiguration('gotoDesign', '');
        if ($gotoDesignId != '') {
            log::add(__CLASS__, 'info', __('Changement design : ', __FILE__) . $gotoDesignId);
            event::add('jeedom::gotoplan', $gotoDesignId);
        }
        */
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
