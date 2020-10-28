<?php
/*
    Пример роутера для сервиса мониторинга сайтов
*/

Route::get('/', function () {
	return view('land');
});

Auth::routes();

Route::group(['middleware' => 'auth'], function () {

	// AssistansController
	Route::get('/assistans', 'AssistansController@index')->name('assistans');
	Route::get('/assistant_deadlines', 'AssistansController@showDeadlines')->name('assistant_deadlines');
	Route::get('/assistant_domains_data', 'AssistansController@showDomainsData')->name('assistant_domains_data');
	Route::get('/assistant_mirrors_list', 'AssistansController@showMirrorsList')->name('assistant_mirrors_list');
	Route::post('add_new_assistan', 'AssistansController@addNewAssistan')->name('add_new_assistan');
	Route::post('update_assistan', 'AssistansController@updateAssistan')->name('update_assistan');

	// ProjectController	
	Route::get('/projects', 'ProjectController@index')->name('projects');
	Route::post('add_new_project', 'ProjectController@addProject')->name('add_new_project');
	Route::post('update_project', 'ProjectController@updateProject')->name('update_project');
	Route::post('render_list', 'ProjectController@renderList')->name('render_list');
	Route::post('change_main', 'ProjectController@changeMain')->name('change_main');
	Route::post('delete_site_in_pro', 'ProjectController@deleteSiteInProject')->name('delete_site_in_pro');
	Route::post('pro_delete', 'ProjectController@deleteProject')->name('pro_delete');
	Route::post('check_one_site', 'ProjectController@checkOneSite')->name('check_one_site');

	// HomeController
	Route::get('/home', 'HomeController@index')->name('home');
	Route::get('/sites_list', 'HomeController@showSitesList')->name('sites_list');
	Route::get('/all_metrics', 'HomeController@index')->name('all_metrics');
	Route::get('/yam_stats', 'HomeController@index')->name('yam_stats');
	Route::get('/yam_monthd', 'HomeController@index')->name('yam_monthd');
	Route::get('/keys_so', 'HomeController@index')->name('keys_so');
	Route::get('/add_new', 'HomeController@addNSindex')->name('add_new');
	Route::get('/faq', 'HomeController@index')->name('faq');
	Route::post('add_new_sites', 'HomeController@addNewSites')->name('add_new_sites');

	// DomainController
	Route::get('/domains_data', 'DomainController@index')->name('domains_data');
	Route::post('delete_site', 'DomainController@deleteSite')->name('delete_site');
	Route::post('change_status_http', 'DomainController@smirRedirectToHttps')->name('change_status_http');;
	Route::post('add_filters_domains', 'DomainController@addFilters');
	Route::post('delete_sites', 'DomainController@deleteCheckSites');
	Route::post('delete_filters_domains', 'DomainController@deleteFilters');

	// TopVisorController
	Route::get('/topvisor', 'TopVisorController@index')->name('topvisor');
	Route::post('update_tvacc', 'TopVisorController@updateAcc')->name('update_tvacc');
	Route::post('add_new_topvisor', 'TopVisorController@add_new_accs')->name('add_new_topvisor');
	Route::post('get_topvisor_data', 'TopVisorController@check_domains');
	Route::post('add_site_to_service', 'TopVisorController@approve_site');
	Route::post('get_summary', 'TopVisorController@update_current_data');
	Route::post('upd_all_domain_data', 'TopVisorController@updateAllCorrectData');
	Route::post('delete_acc', 'TopVisorController@destroy');

	// FieldsController
	Route::get('/additional_fields', 'FieldsController@index')->name('additional_fields');
	Route::post('add_new_column', 'FieldsController@addNewColumn')->name('add_new_column');
	Route::post('update_column', 'FieldsController@updateColumn')->name('update_column');
	Route::post('change_field', 'FieldsController@changeField')->name('add_new_li');

	// DeadlineController
	Route::get('/deadlines', 'DeadlineController@index')->name('domains_deadline');
	Route::post('upd_all_deadline_data', 'DeadlineController@updateAllCorrectData')->name('getCorrectData');
	Route::post('get_deadline_data', 'DeadlineController@getCorrectData');
	Route::post('add_filter_deadlines', 'DeadlineController@addFilters');
	Route::post('delete_filters_deadlines', 'DeadlineController@deleteFilters');

	// LiveInternetController
	Route::get('/li_data', 'LiveInternetController@index')->name('li_data');
	Route::post('add_new_li', 'LiveInternetController@add_new_accs')->name('add_new_li');

	// MirrorsController
	Route::get('/delete_mirror/{id}', 'MirrorsController@deleteMirror')->name('delete_mirror');
	Route::get('/mirrors_list', 'MirrorsController@index')->name('mirrors_list');
	Route::post('add_mirror', 'MirrorsController@addMirror')->name('add_mirror');
	Route::post('show_mirror', 'MirrorsController@pointMirrorList')->name('show_mirror');
	Route::post('upd_all_mirrors_data', 'MirrorsController@updateAllmirData')->name('getCorrectMirData');

	//RknController
	Route::post('change_status_rkn', 'RknController@smirRkn')->name('smir');

	//SmsSenderController
	Route::get('/send_sms', 'SmsSenderController@index')->name('send_sms');
	Route::get('/all_numbers', 'SmsSenderController@showNumbers')->name('all_numbers');
	Route::post('add_new_sms_site', 'SmsSenderController@addNewSmsSite')->name('add_new_sms_site');
	Route::post('update_sms_site', 'SmsSenderController@updateSmsSite')->name('update_sms_site');
	Route::post('delete_sms_site', 'SmsSenderController@deleteSmsSite')->name('delete_sms_site');

	//YandexWebmasterController
	Route::get('/webmaster', 'YandexWebmasterController@index')->name('webmaster');
	Route::get('/ya_web/{id}', 'YandexWebmasterController@show_yaw_details');
	Route::post('add_new_yw', 'YandexWebmasterController@add_new_accs')->name('add_new_yw');
	Route::post('update_ywacc', 'YandexWebmasterController@updateAcc')->name('update_tvacc');
	Route::post('add_to_reindex', 'YandexWebmasterController@addUrlToinex')->name('add_to_reindex');
	Route::post('change_status_yw_tg', 'YandexWebmasterController@changeStatusTelegram')->name('change_status_yw_tg');
	Route::post('get_yw_data', 'YandexWebmasterController@check_domains');
	Route::post('delete_acc_yw', 'YandexWebmasterController@delete');
	Route::post('show_yw', 'YandexWebmasterController@show_summary');

});