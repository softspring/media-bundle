<?php

namespace Softspring\MediaBundle;

class SfsMediaEvents
{
    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseEntityEvent")
     */
    public const ADMIN_TYPES_LIST_INITIALIZE = 'sfs_media.admin.types.list_initialize';

    /**
     * @Event("Softspring\Component\Events\ViewEvent")
     */
    public const ADMIN_TYPES_LIST_VIEW = 'sfs_media.admin.types.list_view';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseEntityEvent")
     */
    public const ADMIN_TYPES_CREATE_INITIALIZE = 'sfs_media.admin.types.create_initialize';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseFormEvent")
     */
    public const ADMIN_TYPES_CREATE_FORM_VALID = 'sfs_media.admin.types.create_form_valid';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseEntityEvent")
     */
    public const ADMIN_TYPES_CREATE_SUCCESS = 'sfs_media.admin.types.create_success';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseFormEvent")
     */
    public const ADMIN_TYPES_CREATE_FORM_INVALID = 'sfs_media.admin.types.create_form_invalid';

    /**
     * @Event("Softspring\Component\Events\ViewEvent")
     */
    public const ADMIN_TYPES_CREATE_VIEW = 'sfs_media.admin.types.create_view';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseEntityEvent")
     */
    public const ADMIN_TYPES_UPDATE_INITIALIZE = 'sfs_media.admin.types.update_initialize';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseFormEvent")
     */
    public const ADMIN_TYPES_UPDATE_FORM_VALID = 'sfs_media.admin.types.update_form_valid';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseEntityEvent")
     */
    public const ADMIN_TYPES_UPDATE_SUCCESS = 'sfs_media.admin.types.update_success';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseFormEvent")
     */
    public const ADMIN_TYPES_UPDATE_FORM_INVALID = 'sfs_media.admin.types.update_form_invalid';

    /**
     * @Event("Softspring\Component\Events\ViewEvent")
     */
    public const ADMIN_TYPES_UPDATE_VIEW = 'sfs_media.admin.types.update_view';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseEntityEvent")
     */
    public const ADMIN_TYPES_DELETE_INITIALIZE = 'sfs_media.admin.types.delete_initialize';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseFormEvent")
     */
    public const ADMIN_TYPES_DELETE_FORM_VALID = 'sfs_media.admin.types.delete_form_valid';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseEntityEvent")
     */
    public const ADMIN_TYPES_DELETE_SUCCESS = 'sfs_media.admin.types.delete_success';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseFormEvent")
     */
    public const ADMIN_TYPES_DELETE_FORM_INVALID = 'sfs_media.admin.types.delete_form_invalid';

    /**
     * @Event("Softspring\Component\Events\ViewEvent")
     */
    public const ADMIN_TYPES_DELETE_VIEW = 'sfs_media.admin.types.delete_view';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseEntityEvent")
     */
    public const ADMIN_TYPES_READ_INITIALIZE = 'sfs_media.admin.types.read_initialize';

    /**
     * @Event("Softspring\Component\Events\ViewEvent")
     */
    public const ADMIN_TYPES_READ_VIEW = 'sfs_media.admin.types.read_view';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseEntityEvent")
     */
    public const ADMIN_MEDIAS_LIST_INITIALIZE = 'sfs_media.admin.medias.list_initialize';

    /**
     * @Event("Softspring\Component\Events\ViewEvent")
     */
    public const ADMIN_MEDIAS_LIST_VIEW = 'sfs_media.admin.medias.list_view';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseEntityEvent")
     */
    public const ADMIN_MEDIAS_CREATE_INITIALIZE = 'sfs_media.admin.medias.create_initialize';

    /**
     * @Event("Softspring\Component\CrudlController\Event\FormPrepareEvent")
     */
    public const ADMIN_MEDIAS_CREATE_FORM_PREPARE = 'sfs_media.admin.medias.create_form_prepare';

    /**
     * @Event("Softspring\Component\Events\FormEvent")
     */
    public const ADMIN_MEDIAS_CREATE_FORM_INIT = 'sfs_media.admin.medias.create_form_init';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseFormEvent")
     */
    public const ADMIN_MEDIAS_CREATE_FORM_VALID = 'sfs_media.admin.medias.create_form_valid';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseEntityEvent")
     */
    public const ADMIN_MEDIAS_CREATE_SUCCESS = 'sfs_media.admin.medias.create_success';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseFormEvent")
     */
    public const ADMIN_MEDIAS_CREATE_FORM_INVALID = 'sfs_media.admin.medias.create_form_invalid';

    /**
     * @Event("Softspring\Component\Events\ViewEvent")
     */
    public const ADMIN_MEDIAS_CREATE_VIEW = 'sfs_media.admin.medias.create_view';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseEntityEvent")
     */
    public const ADMIN_MEDIAS_UPDATE_INITIALIZE = 'sfs_media.admin.medias.update_initialize';

    /**
     * @Event("Softspring\Component\CrudlController\Event\FormPrepareEvent")
     */
    public const ADMIN_MEDIAS_UPDATE_FORM_PREPARE = 'sfs_media.admin.medias.update_form_prepare';

    /**
     * @Event("Softspring\Component\Events\FormEvent")
     */
    public const ADMIN_MEDIAS_UPDATE_FORM_INIT = 'sfs_media.admin.medias.update_form_init';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseFormEvent")
     */
    public const ADMIN_MEDIAS_UPDATE_FORM_VALID = 'sfs_media.admin.medias.update_form_valid';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseEntityEvent")
     */
    public const ADMIN_MEDIAS_UPDATE_SUCCESS = 'sfs_media.admin.medias.update_success';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseFormEvent")
     */
    public const ADMIN_MEDIAS_UPDATE_FORM_INVALID = 'sfs_media.admin.medias.update_form_invalid';

    /**
     * @Event("Softspring\Component\Events\ViewEvent")
     */
    public const ADMIN_MEDIAS_UPDATE_VIEW = 'sfs_media.admin.medias.update_view';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseEntityEvent")
     */
    public const ADMIN_MEDIAS_DELETE_INITIALIZE = 'sfs_media.admin.medias.delete_initialize';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseFormEvent")
     */
    public const ADMIN_MEDIAS_DELETE_FORM_VALID = 'sfs_media.admin.medias.delete_form_valid';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseEntityEvent")
     */
    public const ADMIN_MEDIAS_DELETE_SUCCESS = 'sfs_media.admin.medias.delete_success';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseFormEvent")
     */
    public const ADMIN_MEDIAS_DELETE_FORM_INVALID = 'sfs_media.admin.medias.delete_form_invalid';

    /**
     * @Event("Softspring\Component\Events\ViewEvent")
     */
    public const ADMIN_MEDIAS_DELETE_VIEW = 'sfs_media.admin.medias.delete_view';

    /**
     * @Event("Softspring\Component\CrudlController\Event\GetResponseEntityEvent")
     */
    public const ADMIN_MEDIAS_READ_INITIALIZE = 'sfs_media.admin.medias.read_initialize';

    /**
     * @Event("Softspring\Component\Events\ViewEvent")
     */
    public const ADMIN_MEDIAS_READ_VIEW = 'sfs_media.admin.medias.read_view';
}
