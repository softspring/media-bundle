<?php

namespace Softspring\MediaBundle;

class SfsMediaEvents
{
    public const ADMIN_TYPES_LIST_INITIALIZE = 'sfs_media.admin.types.list_initialize';
    public const ADMIN_TYPES_LIST_VIEW = 'sfs_media.admin.types.list_view';

    public const ADMIN_TYPES_CREATE_INITIALIZE = 'sfs_media.admin.types.create_initialize';
    public const ADMIN_TYPES_CREATE_FORM_VALID = 'sfs_media.admin.types.create_form_valid';
    public const ADMIN_TYPES_CREATE_SUCCESS = 'sfs_media.admin.types.create_success';
    public const ADMIN_TYPES_CREATE_FORM_INVALID = 'sfs_media.admin.types.create_form_invalid';
    public const ADMIN_TYPES_CREATE_VIEW = 'sfs_media.admin.types.create_view';

    public const ADMIN_TYPES_UPDATE_INITIALIZE = 'sfs_media.admin.types.update_initialize';
    public const ADMIN_TYPES_UPDATE_FORM_VALID = 'sfs_media.admin.types.update_form_valid';
    public const ADMIN_TYPES_UPDATE_SUCCESS = 'sfs_media.admin.types.update_success';
    public const ADMIN_TYPES_UPDATE_FORM_INVALID = 'sfs_media.admin.types.update_form_invalid';
    public const ADMIN_TYPES_UPDATE_VIEW = 'sfs_media.admin.types.update_view';

    public const ADMIN_TYPES_DELETE_INITIALIZE = 'sfs_media.admin.types.delete_initialize';
    public const ADMIN_TYPES_DELETE_FORM_VALID = 'sfs_media.admin.types.delete_form_valid';
    public const ADMIN_TYPES_DELETE_SUCCESS = 'sfs_media.admin.types.delete_success';
    public const ADMIN_TYPES_DELETE_FORM_INVALID = 'sfs_media.admin.types.delete_form_invalid';
    public const ADMIN_TYPES_DELETE_VIEW = 'sfs_media.admin.types.delete_view';

    public const ADMIN_TYPES_READ_INITIALIZE = 'sfs_media.admin.types.read_initialize';
    public const ADMIN_TYPES_READ_VIEW = 'sfs_media.admin.types.read_view';

    // MEDIA LIST EVENTS
    public const ADMIN_MEDIAS_LIST_INITIALIZE = 'sfs_media.admin.medias.list_initialize';
    public const ADMIN_MEDIAS_LIST_FILTER_FORM_PREPARE = 'sfs_media.admin.medias.filter_form_prepare';
    public const ADMIN_MEDIAS_LIST_FILTER_FORM_INIT = 'sfs_media.admin.medias.filter_form_init';
    public const ADMIN_MEDIAS_LIST_FILTER = 'sfs_media.admin.medias.list_filter';
    public const ADMIN_MEDIAS_LIST_VIEW = 'sfs_media.admin.medias.list_view';
    public const ADMIN_MEDIAS_LIST_EXCEPTION = 'sfs_media.admin.medias.list_exception';
    // MEDIA CREATE EVENTS
    public const ADMIN_MEDIAS_CREATE_INITIALIZE = 'sfs_media.admin.medias.create_initialize';
    public const ADMIN_MEDIAS_CREATE_ENTITY = 'sfs_media.admin.medias.create_create_entity';
    public const ADMIN_MEDIAS_CREATE_FORM_PREPARE = 'sfs_media.admin.medias.create_form_prepare';
    public const ADMIN_MEDIAS_CREATE_FORM_INIT = 'sfs_media.admin.medias.create_form_init';
    public const ADMIN_MEDIAS_CREATE_FORM_VALID = 'sfs_media.admin.medias.create_form_valid';
    public const ADMIN_MEDIAS_CREATE_APPLY = 'sfs_media.admin.medias.create_apply';
    public const ADMIN_MEDIAS_CREATE_SUCCESS = 'sfs_media.admin.medias.create_success';
    public const ADMIN_MEDIAS_CREATE_FAILURE = 'sfs_media.admin.medias.create_failure';
    public const ADMIN_MEDIAS_CREATE_FORM_INVALID = 'sfs_media.admin.medias.create_form_invalid';
    public const ADMIN_MEDIAS_CREATE_VIEW = 'sfs_media.admin.medias.create_view';
    public const ADMIN_MEDIAS_CREATE_EXCEPTION = 'sfs_media.admin.medias.create_exception';
    // MEDIA CREATE AJAX EVENTS
    public const ADMIN_MEDIAS_CREATE_AJAX_INITIALIZE = 'sfs_media.admin.medias.create_ajax_initialize';
    public const ADMIN_MEDIAS_CREATE_AJAX_ENTITY = 'sfs_media.admin.medias.create_ajax_create_entity';
    public const ADMIN_MEDIAS_CREATE_AJAX_FORM_PREPARE = 'sfs_media.admin.medias.create_ajax_form_prepare';
    public const ADMIN_MEDIAS_CREATE_AJAX_FORM_INIT = 'sfs_media.admin.medias.create_ajax_form_init';
    public const ADMIN_MEDIAS_CREATE_AJAX_FORM_VALID = 'sfs_media.admin.medias.create_ajax_form_valid';
    public const ADMIN_MEDIAS_CREATE_AJAX_APPLY = 'sfs_media.admin.medias.create_ajax_apply';
    public const ADMIN_MEDIAS_CREATE_AJAX_SUCCESS = 'sfs_media.admin.medias.create_ajax_success';
    public const ADMIN_MEDIAS_CREATE_AJAX_FAILURE = 'sfs_media.admin.medias.create_ajax_failure';
    public const ADMIN_MEDIAS_CREATE_AJAX_FORM_INVALID = 'sfs_media.admin.medias.create_ajax_form_invalid';
    public const ADMIN_MEDIAS_CREATE_AJAX_VIEW = 'sfs_media.admin.medias.create_ajax_view';
    public const ADMIN_MEDIAS_CREATE_AJAX_EXCEPTION = 'sfs_media.admin.medias.create_ajax_exception';
    // MEDIA UPDATE EVENTS
    public const ADMIN_MEDIAS_UPDATE_INITIALIZE = 'sfs_media.admin.medias.update_initialize';
    public const ADMIN_MEDIAS_UPDATE_LOAD_ENTITY = 'sfs_media.admin.medias.update_load_entity';
    public const ADMIN_MEDIAS_UPDATE_NOT_FOUND = 'sfs_media.admin.medias.update_not_found';
    public const ADMIN_MEDIAS_UPDATE_FOUND = 'sfs_media.admin.medias.update_found';
    public const ADMIN_MEDIAS_UPDATE_FORM_PREPARE = 'sfs_media.admin.medias.update_form_prepare';
    public const ADMIN_MEDIAS_UPDATE_FORM_INIT = 'sfs_media.admin.medias.update_form_init';
    public const ADMIN_MEDIAS_UPDATE_FORM_VALID = 'sfs_media.admin.medias.update_form_valid';
    public const ADMIN_MEDIAS_UPDATE_APPLY = 'sfs_media.admin.medias.update_apply';
    public const ADMIN_MEDIAS_UPDATE_SUCCESS = 'sfs_media.admin.medias.update_success';
    public const ADMIN_MEDIAS_UPDATE_FAILURE = 'sfs_media.admin.medias.update_failure';
    public const ADMIN_MEDIAS_UPDATE_FORM_INVALID = 'sfs_media.admin.medias.update_form_invalid';
    public const ADMIN_MEDIAS_UPDATE_VIEW = 'sfs_media.admin.medias.update_view';
    public const ADMIN_MEDIAS_UPDATE_EXCEPTION = 'sfs_media.admin.medias.update_exception';
    // MEDIA DELETE EVENTS
    public const ADMIN_MEDIAS_DELETE_INITIALIZE = 'sfs_media.admin.medias.delete_initialize';
    public const ADMIN_MEDIAS_DELETE_LOAD_ENTITY = 'sfs_media.admin.medias.delete_load_entity';
    public const ADMIN_MEDIAS_DELETE_NOT_FOUND = 'sfs_media.admin.medias.delete_not_found';
    public const ADMIN_MEDIAS_DELETE_FOUND = 'sfs_media.admin.medias.delete_found';
    public const ADMIN_MEDIAS_DELETE_FORM_PREPARE = 'sfs_media.admin.medias.delete_form_prepare';
    public const ADMIN_MEDIAS_DELETE_FORM_INIT = 'sfs_media.admin.medias.delete_form_init';
    public const ADMIN_MEDIAS_DELETE_FORM_VALID = 'sfs_media.admin.medias.delete_form_valid';
    public const ADMIN_MEDIAS_DELETE_APPLY = 'sfs_media.admin.medias.delete_apply';
    public const ADMIN_MEDIAS_DELETE_SUCCESS = 'sfs_media.admin.medias.delete_success';
    public const ADMIN_MEDIAS_DELETE_FAILURE = 'sfs_media.admin.medias.delete_failure';
    public const ADMIN_MEDIAS_DELETE_FORM_INVALID = 'sfs_media.admin.medias.delete_form_invalid';
    public const ADMIN_MEDIAS_DELETE_VIEW = 'sfs_media.admin.medias.delete_view';
    public const ADMIN_MEDIAS_DELETE_EXCEPTION = 'sfs_media.admin.medias.delete_exception';
    // MEDIA READ EVENTS
    public const ADMIN_MEDIAS_READ_INITIALIZE = 'sfs_media.admin.medias.read_initialize';
    public const ADMIN_MEDIAS_READ_LOAD_ENTITY = 'sfs_media.admin.medias.read_load_entity';
    public const ADMIN_MEDIAS_READ_NOT_FOUND = 'sfs_media.admin.medias.read_not_found';
    public const ADMIN_MEDIAS_READ_FOUND = 'sfs_media.admin.medias.read_found';
    public const ADMIN_MEDIAS_READ_VIEW = 'sfs_media.admin.medias.read_view';
    public const ADMIN_MEDIAS_READ_EXCEPTION = 'sfs_media.admin.medias.read_exception';
}
