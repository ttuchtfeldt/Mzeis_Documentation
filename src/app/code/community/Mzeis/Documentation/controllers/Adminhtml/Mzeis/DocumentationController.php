<?php

class Mzeis_Documentation_Adminhtml_Mzeis_DocumentationController extends Mage_Adminhtml_Controller_Action
{
    /**
     * @return void
     */
    protected function _initAction()
    {
        $this->loadLayout()
             ->_setActiveMenu('system/mzeis_documentation');
    }

    /**
     * @return Mzeis_Documentation_Model_Page
     */
    protected function _initPage()
    {
        if (Mage::registry('current_page') === null) {
            $name = $this->getRequest()->getParam('page');
            $module = $this->getRequest()->getParam('module');

            $page = Mage::getModel('mzeis_documentation/page');
            $page->setName($name);
            $page->setModule($module);
            if (!is_null($name)) {
                $page->loadByName($name);
            }
            Mage::register('current_page', $page);
        }
        return Mage::registry('current_page');
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        $action = strtolower($this->getRequest()->getActionName());
        $isModifyingAction = false;

        switch ($action) {
            case 'delete':
                $aclResource = 'system/mzeis_documentation/delete';
                $isModifyingAction = true;
                break;
            case 'edit':
            case 'rename':
            case 'renamepost':
            case 'save':
                $aclResource = 'system/mzeis_documentation/edit';
                $isModifyingAction = true;
            break;
            default:
                $aclResource = 'system/mzeis_documentation';
                break;

        }

        $page = $this->_initPage();

        if ($isModifyingAction === true && ($page->isModulePage() ||  $page->sourceIsFile())) {
            return false;
        }

        return Mage::getSingleton('admin/session')->isAllowed($aclResource);
    }

    public function deleteAction()
    {
        if ($name = $this->getRequest()->getParam('page')) {
            try {
                $model = Mage::getModel('mzeis_documentation/page');
                $model->load($name, 'name');
                $model->delete();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('mzeis_documentation')->__('The page has been deleted.'));
                $this->_redirect('*/*/');
                return;
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/edit', array('_query' => array('page' => $name)));
                return;
            }
        }
        Mage::getSingleton('adminhtml/session')->addError(Mage::helper('mzeis_documentation')->__('Unable to find a page to delete.'));
        $this->_redirect('*/*/');
    }

    public function editAction()
    {
        $this->_initPage();
        $this->_initAction();
        $this->renderLayout();
    }

    public function indexAction()
    {
        $this->getRequest()->setParam('page', Mage::helper('mzeis_documentation')->getHomepageName());
        Mage::unregister('current_page');
        $this->_forward('view');
    }

    public function renameAction()
    {
        $this->_initPage();
        $this->_initAction();
        $this->renderLayout();
    }

    public function renamePostAction()
    {
        $page = $this->_initPage();

        if ($data = $this->getRequest()->getPost()) {
            try {
                if (!$page->getId()) {
                    Mage::throwException(Mage::helper('mzeis_documentation')->__('This page doesn\'t exist.'));
                }

                $newName = $data['new_name'];

                if ($newName === $page->getName()) {
                    $this->_getSession()->addSuccess(
                        Mage::helper('mzeis_documentation')->__('The new and the old name are identical. No changes have been saved.')
                    );
                } else {
                    $nameCheckPage = Mage::getModel('mzeis_documentation/page')->loadByName($newName);
                    if ($nameCheckPage->getId()) {
                        Mage::throwException(Mage::helper('mzeis_documentation')->__("The page '%s' does already exist.", $newName));
                    }

                    $oldName = $page->getName();
                    $page->setName($newName);
                    $page->save();
                    $this->_getSession()->addSuccess(
                        Mage::helper('mzeis_documentation')->__('The new name has been saved.')
                    );

                    $page->renameLinks($oldName, $newName);
                    $this->_getSession()->addSuccess(
                        Mage::helper('mzeis_documentation')->__('The links to this page have been renamed.')
                    );
                }
            } catch (Exception $e) {
                Mage::logException($e);
                $this->_getSession()->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/view', array('_query' => array('page' => $page->getName()), '_current' => true));
    }

    public function saveAction()
    {
        $back = $this->getRequest()->getParam('back', false);
        $page = $this->_initPage();

        if ($data = $this->getRequest()->getPost()) {
            try {
                $page->addData($data);

                if (!$page->getId()) {
                    $page->setCreatedUser(Mage::getSingleton('admin/session')->getUser()->getUsername());
                }
                $page->setUpdatedUser(Mage::getSingleton('admin/session')->getUser()->getUsername());
                $page->save();
                $this->_getSession()->addSuccess(
                    Mage::helper('mzeis_documentation')->__('The page has been saved.')
                );
            } catch (Exception $e) {
                Mage::logException($e);
                $this->_getSession()->addError($e->getMessage());
                $back = true;
            }
        }

        if ($back) {
            $this->_redirect('*/*/edit', array('_query' => array('page' => $page->getName()), '_current' => true));
            return;
        }
        $this->_redirect('*/*/view', array('_query' => array('page' => $page->getName()), '_current' => true));
    }

    public function viewAction()
    {
        $this->_initAction();

        $page = $this->_initPage();
        $block = $this->getLayout()->getBlock('mzeis.documentation.page.view');
        if ($block) {
            $block->setPage($page);
        }
        $this->renderLayout();
    }
}
