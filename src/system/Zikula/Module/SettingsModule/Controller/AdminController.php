<?php
/**
 * Copyright Zikula Foundation 2009 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv3 (or at your option, any later version).
  *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

namespace Zikula\Module\SettingsModule\Controller;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zikula_View;
use ModUtil;
use SecurityUtil;
use System;
use DateUtil;
use SessionUtil;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * administrative controllers for the settings module
 */
class AdminController extends \Zikula_AbstractController
{
    /**
     * Post initialise.
     *
     * @return void
     */
    protected function postInitialize()
    {
        // In this controller we do not want caching.
        $this->view->setCaching(Zikula_View::CACHE_DISABLED);
    }

    /**
     * entry point for the module
     *
     * @return RedirectResponse
     */
    public function mainAction()
    {
        // Security check will be done in modifyconfig()
        return new RedirectResponse(System::normalizeUrl(ModUtil::url($this->name, 'admin', 'modifyconfig')));
    }

    /**
     * display the main site settings form
     *
     * @return Response symfony response object
     *
     * @throws AccessDeniedException Thrown if the user doesn't have admin access to the module
     */
    public function modifyconfigAction()
    {
        // security check
        if (!SecurityUtil::checkPermission('ZikulaSettingsModule::', '::', ACCESS_ADMIN)) {
            throw new AccessDeniedException();
        }


        // localise page title
        $pagetitle = System::getVar('pagetitle', '%pagetitle%');
        $pagetitle = str_replace('%pagetitle%', $this->__('%pagetitle%'), $pagetitle);
        $pagetitle = str_replace('%sitename%', $this->__('%sitename%'), $pagetitle);
        $pagetitle = str_replace('%modulename%', $this->__('%modulename%'), $pagetitle);
        $this->view->assign('pagetitle', $pagetitle);

        return $this->view->fetch('Admin/modifyconfig.tpl');
    }

    /**
     * update main site settings
     *
     * @return RedirectResponse
     *
     * @throws AccessDeniedException Thrown if the user doesn't have admin access to the module
     */
    public function updateconfigAction()
    {
        $this->checkCsrfToken();

        // security check
        if (!SecurityUtil::checkPermission('ZikulaSettingsModule::', '::', ACCESS_ADMIN)) {
            throw new AccessDeniedException();
        }

        // get settings from form
        $settings = $this->request->request->get('settings', null);

        // if this form wasn't posted to redirect back
        if ($settings === null) {
            return new RedirectResponse(System::normalizeUrl(ModUtil::url($this->name, 'admin', 'modifyconfig')));
        }

        // validate the entry point
        $falseEntryPoints = array('admin.php', 'ajax.php', 'install.php', 'upgrade.php', 'user.php', 'mo2json.php', 'jcss.php');
        $entryPointExt = pathinfo($settings['entrypoint'], PATHINFO_EXTENSION);

        if (in_array($settings['entrypoint'], $falseEntryPoints) || !file_exists($settings['entrypoint']) || strtolower($entryPointExt) != 'php') {
            $this->request->getSession()->getFlashbag()->add('error', $this->__('Error! Either you entered an invalid entry point, or else the file specified as being the entry point was not found in the Zikula root directory.'));
            return new RedirectResponse(System::normalizeUrl(ModUtil::url($this->name, 'admin', 'modifyconfig')));
        }

        $permachecks = true;
        $settings['permasearch'] = mb_ereg_replace(' ', '', $settings['permasearch']);
        $settings['permareplace'] = mb_ereg_replace(' ', '', $settings['permareplace']);
        if (mb_ereg(',$', $settings['permasearch'])) {
            $this->request->getSession()->getFlashbag()->add('error', $this->__('Error! In your permalink settings, strings cannot be terminated with a comma.'));
            return new RedirectResponse(System::normalizeUrl(ModUtil::url($this->name, 'admin', 'modifyconfig')));
        }

        if (mb_strlen($settings['permasearch']) == 0) {
            $permasearchCount = 0;
        } else {
            $permasearchCount = (!mb_ereg(',', $settings['permasearch']) && mb_strlen($settings['permasearch'] > 0) ? 1 : count(explode(',', $settings['permasearch'])));
        }

        if (mb_strlen($settings['permareplace']) == 0) {
            $permareplaceCount = 0;
        } else {
            $permareplaceCount = (!mb_ereg(',', $settings['permareplace']) && mb_strlen($settings['permareplace'] > 0) ? 1 : count(explode(',', $settings['permareplace'])));
        }

        if ($permareplaceCount !== $permasearchCount) {
            $this->request->getSession()->getFlashbag()->add('error', $this->__('Error! In your permalink settings, the search list and the replacement list for permalink cleansing have a different number of comma-separated elements. If you have 3 elements in the search list then there must be 3 elements in the replacement list.'));
            return new RedirectResponse(System::normalizeUrl(ModUtil::url($this->name, 'admin', 'modifyconfig')));
        }

        if ($settings['startpage']) {
            if (empty($settings['starttype']) || empty($settings['startfunc'])) {
                $this->request->getSession()->getFlashbag()->add('error', $this->__('Error! When setting a startpage, starttype and startfunc are required fields.'));
                return new RedirectResponse(System::normalizeUrl(ModUtil::url($this->name, 'admin', 'modifyconfig')));
            }
        }

        if (!$permachecks) {
            unset($settings['permasearch']);
            unset($settings['permareplace']);
        }

        // delocalise page title
        $settings['pagetitle'] = str_replace($this->__('%pagetitle%'), '%pagetitle%', $settings['pagetitle']);
        $settings['pagetitle'] = str_replace($this->__('%sitename%'), '%sitename%', $settings['pagetitle']);
        $settings['pagetitle'] = str_replace($this->__('%modulename%'), '%modulename%', $settings['pagetitle']);

        // Write the vars
        $configvars = ModUtil::getVar(ModUtil::CONFIG_MODULE);
        foreach ($settings as $key => $value) {
            $oldvalue = System::getVar($key);
            if ($value != $oldvalue) {
                System::setVar($key, $value);
            }
        }

        // clear all cache and compile directories
        ModUtil::apiFunc('ZikulaSettingsModule', 'admin', 'clearallcompiledcaches');

        $this->request->getSession()->getFlashbag()->add('status', $this->__('Done! Saved module configuration.'));

        return new RedirectResponse(System::normalizeUrl(ModUtil::url($this->name, 'admin', 'modifyconfig')));
    }

    /**
     * display the ML settings form
     *
     * @return Response symfony response object
     *
     * @throws AccessDeniedException Thrown if the user doesn't have admin access to the module
     */
    public function multilingualAction()
    {
        // security check
        if (!SecurityUtil::checkPermission('ZikulaSettingsModule::', '::', ACCESS_ADMIN)) {
            throw new AccessDeniedException();
        }

        // get the server timezone and pass it to template - we should not allow to change this
        $this->view->assign('timezone_server', DateUtil::getTimezone());
        $this->view->assign('timezone_server_abbr', DateUtil::getTimezoneAbbr());

        return $this->response($this->view->fetch('Admin/multilingual.tpl'));
    }

    /**
     * update ML settings
     *
     * @return RedirectResponse
     *
     * @throws AccessDeniedException Thrown if the user doesn't have admin access to the module
     */
    public function updatemultilingualAction()
    {
        $this->checkCsrfToken();

        // security check
        if (!SecurityUtil::checkPermission('ZikulaSettingsModule::', '::', ACCESS_ADMIN)) {
            throw new AccessDeniedException();
        }

        $url = ModUtil::url('ZikulaSettingsModule', 'admin', 'multilingual');

        $settings = array('mlsettings_language_i18n' => 'language_i18n',
                'mlsettings_timezone_offset' => 'timezone_offset',
                'mlsettings_timezone_server' => 'timezone_server',
                'mlsettings_multilingual' => 'multilingual',
                'mlsettings_language_detect' => 'language_detect',
                'mlsettings_languageurl' => 'languageurl',
                'mlsettings_timezone_adjust' => 'tzadjust');

        // we can't detect language if multilingual feature is off so reset this to false
        if ($this->request->request->get('mlsettings_multilingual', null) == 0) {
            if (System::getVar('language_detect')) {
                System::setVar('language_detect', 0);
                unset($settings['mlsettings_language_detect']);
                $this->request->getSession()->getFlashbag()->add('status', $this->__('Notice: Language detection is automatically disabled when multi-lingual features are disabled.'));
            }

            $deleteLangUrl = true;
        }

        if (isset($deleteLangUrl)) {
            // reset language settings
            SessionUtil::delVar('language');
            $url = preg_replace('#(.*)(&lang=[a-z-]{2,5})(.*)#i', '$1$3', $url);
        }

        // Write the vars
        $configvars = ModUtil::getVar(ModUtil::CONFIG_MODULE);
        foreach ($settings as $formname => $varname) {
            $newvalue = $this->request->request->get($formname, null);
            $oldvalue = System::getVar($varname);
            if ($newvalue != $oldvalue) {
                System::setVar($varname, $newvalue);
            }
        }

        // clear all cache and compile directories
        ModUtil::apiFunc('ZikulaSettingsModule', 'admin', 'clearallcompiledcaches');

        // all done successfully
        $this->request->getSession()->getFlashbag()->add('status', $this->__('Done! Saved localisation settings.'));

        return new RedirectResponse(System::normalizeUrl($url));
    }

    /**
     * Displays the content of {@link phpinfo()}.
     *
     * @return Response symfony response object
     *
     * @throws AccessDeniedException Thrown if the user doesn't have admin access to the module
     */
    public function phpinfoAction()
    {
        if (!SecurityUtil::checkPermission('ZikulaSettingsModule::', '::', ACCESS_ADMIN)) {
            throw new AccessDeniedException();
        }

        ob_start();
        phpinfo();
        $phpinfo = ob_get_contents();
        ob_end_clean();
        $phpinfo = str_replace("module_Zend Optimizer", "module_Zend_Optimizer", preg_replace ('%^.*<body>(.*)</body>.*$%ms', '$1', $phpinfo));

        $this->view->assign('phpinfo', $phpinfo);

        return $this->response($this->view->fetch('Admin/phpinfo.tpl'));
    }
}
