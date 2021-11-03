<?php
namespace PayioLtd\Payio\Model\Config\Backend;

class Image extends \Magento\Config\Model\Config\Backend\Image
{
    /**
     * The tail part of directory path for uploading
     */
    const UPLOAD_DIR = 'payments/logo';

    /**
     * Upload max file size in kilobytes
     *
     * @var int
     */
    protected $_maxFileSize = 2048;
    
   /**
    * Return path to directory for upload file
    *
    * @return string
    * @throw \Magento\Framework\Exception\LocalizedException
    */
    protected function _getUploadDir()
    {
        return $this->_mediaDirectory->getAbsolutePath($this->_appendScopeInfo(self::UPLOAD_DIR));
    }

    /**
     * Makes a decision about whether to add info about the scope.
     *
     * @return boolean
     */
    protected function _addWhetherScopeInfo()
    {
        return true;
    }

    /**
     * Getter for allowed extensions of uploaded files.
     *
     * @return string[]
     */
    protected function getAllowedExtensions()
    {
        return ['jpg', 'jpeg', 'gif', 'png', 'svg'];
    }

    /**
     * @return string|null
     */
    protected function getTmpFileName()
    {
        return $this->_requestData->getTmpName($this->getPath());
    }

    /**
     *
     * @return $this
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        $deleteFlag = is_array($value) && !empty($value['delete']);
        $fileTmpName = $this->getTmpFileName();

        if ($this->getOldValue() && ($fileTmpName || $deleteFlag)) {
            $this->_mediaDirectory->delete(self::UPLOAD_DIR . '/' . $this->getOldValue());
        }
        return parent::beforeSave();
    }
}
