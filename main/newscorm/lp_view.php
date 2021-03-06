<?php
/* For licensing terms, see /license.txt */

use ChamiloSession as Session;
use Chamilo\CoreBundle\Framework\Container;

/**
 * This file was originally the copy of document.php, but many modifications happened since then ;
 * the direct file view is not needed anymore, if the user uploads a scorm zip file, a directory
 * will be automatically created for it, and the files will be uncompressed there for example ;
 *
 * @package chamilo.learnpath
 * @author Yannick Warnier <ywarnier@beeznest.org> - redesign
 * @author Denes Nagy, principal author
 * @author Isthvan Mandak, several new features
 * @author Roan Embrechts, code improvements and refactoring
 */

$use_anonymous = true;

Session::write('whereami', 'lp/view');
$this_section = SECTION_COURSES;

if ($lp_controller_touched != 1) {
    header('location: lp_controller.php?action=view&item_id=' . intval($_REQUEST['item_id']));
    exit;
}

//require_once '../inc/global.inc.php';

//To prevent the template class
$show_learnpath = true;

api_protect_course_script();

$lp_id = !empty($_GET['lp_id']) ? intval($_GET['lp_id']) : 0;
$sessionId = api_get_session_id();

// Check if the learning path is visible for student - (LP requisites)

if (!api_is_platform_admin()) {
    if (
        !api_is_allowed_to_edit(null, true) &&
        !learnpath::is_lp_visible_for_student($lp_id, api_get_user_id())
    ) {
        api_not_allowed(true);
    }
}

// Checking visibility (eye icon)
$visibility = api_get_item_visibility(
    api_get_course_info(),
    TOOL_LEARNPATH,
    $lp_id,
    $action,
    api_get_user_id(),
    $sessionId
);

if (!api_is_allowed_to_edit(false, true, false, false) && intval($visibility) == 0) {
    api_not_allowed(true);
}
$learnPath = learnpath::getCurrentLpFromSession();
if (empty($learnPath)) {
    api_not_allowed(true);
}

$debug = 0;

if ($debug) {
    error_log('------ Entering lp_view.php -------');
}

$learnPath->error = '';
$lp_item_id = $learnPath->get_current_item_id();
$lpType = $learnPath->get_type();

if (!$is_allowed_to_edit) {
    $categoryId = $_SESSION['oLP']->getCategoryId();
    $em = Database::getManager();
    if (!empty($categoryId)) {
        /** @var \Chamilo\CourseBundle\Entity\CLpCategory $category */
        $category = $em->getRepository('ChamiloCourseBundle:CLpCategory')->find($categoryId);
        if ($category) {
            $users = $category->getUsers();
            if (!empty($users) && $users->count() > 0) {
                $user = UserManager::getRepository()->find($user_id);
                if (!$category->hasUserAdded($user)) {
                    api_not_allowed(true);
                }
            }
        }
    }
}

$course_code = api_get_course_id();
$course_id = api_get_course_int_id();
$user_id = api_get_user_id();
$platform_theme = api_get_setting('stylesheets'); // Platform's css.
$my_style = $platform_theme;

$htmlHeadXtra[] = '<script type="text/javascript">
<!--
var jQueryFrameReadyConfigPath = \''.api_get_path(WEB_LIBRARY_PATH).'javascript/jquery.min.js\';
-->
</script>';
/*
$htmlHeadXtra[] = '<script type="text/javascript" src="'.api_get_path(
        WEB_LIBRARY_JS_PATH
    ).'jquery.frameready.js"></script>';
$htmlHeadXtra[] = '<script src="'.api_get_path(
        WEB_LIBRARY_JS_PATH
    ).'jquery.lp_minipanel.js" type="text/javascript" language="javascript"></script>';
$htmlHeadXtra[] = '<script>
$(document).ready(function() {
    $("div#log_content_cleaner").bind("click", function() {
        $("div#log_content").empty();
    });
});
var chamilo_xajax_handler = window.oxajax;
</script>';*/

if ($learnPath->mode == 'embedframe' || $learnPath->get_hide_toc_frame() == 1) {
    $htmlHeadXtra[] = 'hello';
}

//Impress js
if ($learnPath->mode == 'impress') {
    $lp_id = $learnPath->get_id();
    $url = api_get_path(WEB_CODE_PATH) . "newscorm/lp_impress.php?lp_id=$lp_id&" . api_get_cidreq();
    header("Location: $url");
    exit;
}

// Prepare variables for the test tool (just in case) - honestly, this should disappear later on.
Session::write('scorm_view_id', $learnPath->get_view_id());
Session::write('scorm_item_id', $lp_item_id);

$exerciseFromSession = Session::read('exerciseResult');
// Reinit exercises variables to avoid spacename clashes (see exercise tool)
if (isset($exerciseResult) || isset($exerciseFromSession)) {
    Session::erase('exerciseResult');
    Session::erase('objExercise');
    Session::erase('questionList');
}

// additional APIs
$htmlHeadXtra[] = '<script>
chamilo_courseCode = "' . $course_code . '";
</script>';

// Storage API
$htmlHeadXtra[] = '<script>
var sv_user = \'' . api_get_user_id() . '\';
var sv_course = chamilo_courseCode;
var sv_sco = \'' . $lp_id . '\';
</script>'; // FIXME fetch sco and userid from a more reliable source directly in sotrageapi.js

/**
 * Get a link to the corresponding document.
 */
if ($debug) {
    error_log(" src: $src ");
    error_log(" lp_type: $lpType ");
}

$get_toc_list = $learnPath->get_toc();
$get_teacher_buttons = $learnPath->get_teacher_toc_buttons();

$type_quiz = false;
foreach ($get_toc_list as $toc) {
    if ($toc['id'] == $lp_item_id && $toc['type'] == 'quiz') {
        $type_quiz = true;
    }
}

if (!isset($src)) {
    $src = null;
    switch ($lpType) {
        case 1:
            $learnPath->stop_previous_item();
            $htmlHeadXtra[] = '<script src="scorm_api.php?'.api_get_cidreq(
                ).'" type="text/javascript" language="javascript"></script>';
            $preReqCheck = $learnPath->prerequisites_match($lp_item_id);
            if ($preReqCheck === true) {
                $src = $learnPath->get_link(
                    'http',
                    $lp_item_id,
                    $get_toc_list
                );

                // Prevents FF 3.6 + Adobe Reader 9 bug see BT#794 when calling a pdf file in a LP.
                $file_info = parse_url($src);
                if (isset($file_info['path'])) {
                    $file_info = pathinfo($file_info['path']);
                }

                if (
                    isset($file_info['extension']) &&
                    api_strtolower(substr($file_info['extension'], 0, 3) == 'pdf')
                ) {
                    $src = api_get_path(WEB_CODE_PATH).'newscorm/lp_view_item.php?lp_item_id='.$lp_item_id.'&'.api_get_cidreq();
                }

                $src = $learnPath->fixBlockedLinks($src);

                $learnPath->start_current_item(
                ); // starts time counter manually if asset
            } else {
                $src = 'blank.php?error=prerequisites';
            }
            break;
        case 2:
            // save old if asset
            $learnPath->stop_previous_item(); // save status manually if asset
            $htmlHeadXtra[] = '<script src="scorm_api.php?'.api_get_cidreq(
                ).'" type="text/javascript" language="javascript"></script>';
            $preReqCheck = $learnPath->prerequisites_match($lp_item_id);
            if ($preReqCheck === true) {
                $src = $learnPath->get_link('http', $lp_item_id, $get_toc_list);
                $learnPath->start_current_item(
                ); // starts time counter manually if asset
            } else {
                $src = 'blank.php?error=prerequisites';
            }
            break;
        case 3:
            // aicc
            $learnPath->stop_previous_item(); // save status manually if asset
            $htmlHeadXtra[] = '<script src="'.$learnPath->get_js_lib(
                ).'" type="text/javascript" language="javascript"></script>';
            $preReqCheck = $learnPath->prerequisites_match($lp_item_id);
            if ($preReqCheck === true) {
                $src = $learnPath->get_link(
                    'http',
                    $lp_item_id,
                    $get_toc_list
                );
                $learnPath->start_current_item(
                ); // starts time counter manually if asset
            } else {
                $src = 'blank.php';
            }
            break;
        case 4:
            break;
    }
}

$autostart = 'true';
// Update status, total_time from lp_item_view table when you finish the exercises in learning path.

if ($debug) {
    error_log('$type_quiz: ' . $type_quiz);
    error_log('$_REQUEST[exeId]: ' . intval($_REQUEST['exeId']));
    error_log('$lp_id: ' . $lp_id);
    error_log('$_GET[lp_item_id]: ' . intval($_GET['lp_item_id']));
}

if (
    !empty($_REQUEST['exeId']) &&
    isset($lp_id) &&
    isset($_GET['lp_item_id'])
) {
    global $src;
    $learnPath->items[$learnPath->current]->write_to_db();

    $TBL_TRACK_EXERCICES = Database::get_main_table(TABLE_STATISTIC_TRACK_E_EXERCISES);
    $TBL_LP_ITEM_VIEW = Database::get_course_table(TABLE_LP_ITEM_VIEW);
    $TBL_LP_ITEM = Database::get_course_table(TABLE_LP_ITEM);
    $safe_item_id = intval($_GET['lp_item_id']);
    $safe_id = $lp_id;
    $safe_exe_id = intval($_REQUEST['exeId']);

    if (
        $safe_id == strval(intval($safe_id)) &&
        $safe_item_id == strval(intval($safe_item_id))
    ) {
        $sql = 'SELECT start_date, exe_date, exe_result, exe_weighting, exe_exo_id
                FROM ' . $TBL_TRACK_EXERCICES . '
                WHERE exe_id = ' . $safe_exe_id;
        $res = Database::query($sql);
        $row_dates = Database::fetch_array($res);

        $time_start_date = api_strtotime($row_dates['start_date'], 'UTC');
        $time_exe_date = api_strtotime($row_dates['exe_date'], 'UTC');

        $mytime = ((int) $time_exe_date - (int) $time_start_date);
        $score = (float) $row_dates['exe_result'];
        $max_score = (float) $row_dates['exe_weighting'];

        $sql = "UPDATE $TBL_LP_ITEM SET
                    max_score = '$max_score'
                WHERE c_id = $course_id AND id = '" . $safe_item_id . "'";
        Database::query($sql);

        $sql = "SELECT id FROM $TBL_LP_ITEM_VIEW
                WHERE
                    c_id = $course_id AND
                    lp_item_id = '$safe_item_id' AND
                    lp_view_id = '".$learnPath->lp_view_id."'
                ORDER BY id DESC
                LIMIT 1";
        $res_last_attempt = Database::query($sql);

        if (Database::num_rows($res_last_attempt) && !api_is_invitee()) {
            $row_last_attempt = Database::fetch_row($res_last_attempt);
            $lp_item_view_id = $row_last_attempt[0];

            $exercise = new Exercise(api_get_course_int_id());
            $exercise->read($row_dates['exe_exo_id']);
            $status = 'completed';

            if (!empty($exercise->pass_percentage)) {
                $status = 'failed';
                $success = ExerciseLib::is_success_exercise_result(
                    $score,
                    $max_score,
                    $exercise->pass_percentage
                );
                if ($success) {
                    $status = 'passed';
                }
            }

            $sql = "UPDATE $TBL_LP_ITEM_VIEW SET
                        status = '$status',
                        score = $score,
                        total_time = $mytime
                    WHERE id='" . $lp_item_view_id . "' AND c_id = $course_id ";

            if ($debug) {
                error_log($sql);
            }

            Database::query($sql);

            $sql = "UPDATE $TBL_TRACK_EXERCICES SET
                        orig_lp_item_view_id = $lp_item_view_id
                    WHERE exe_id = " . $safe_exe_id;
            Database::query($sql);
        }
    }
    if (intval($_GET['fb_type']) > 0) {
        $src = 'blank.php?msg=exerciseFinished';
    } else {
        $src = api_get_path(WEB_CODE_PATH) . 'exercice/result.php?origin=learnpath&id=' . $safe_exe_id.'&'.api_get_cidreq();

        if ($debug) {
            error_log('Calling URL: ' . $src);
        }
    }
    $autostart = 'false';
}

$learnPath->set_previous_item($lp_item_id);
$nameTools = Security::remove_XSS($learnPath->get_name());

$save_setting = api_get_setting('course.show_navigation_menu');
global $_setting;
$_setting['show_navigation_menu'] = 'false';
$scorm_css_header = true;
$lp_theme_css = $learnPath->get_theme();
// Sets the css theme of the LP this call is also use at the frames (toc, nav, message).

if ($learnPath->mode == 'fullscreen') {
    $htmlHeadXtra[] = "<script>window.open('$src','content_id','toolbar=0,location=0,status=0,scrollbars=1,resizable=1');</script>";
}

// Not in fullscreen mode.
// Check if audio recorder needs to be in studentview.
if (isset($_SESSION['status']) && $_SESSION['status'][$course_code] == 5) {
    $audio_recorder_studentview = true;
} else {
    $audio_recorder_studentview = false;
}

// Set flag to ensure lp_header.php is loaded by this script (flag is unset in lp_header.php).
Session::write('loaded_lp_view', true);

$display_none = '';
$margin_left = '340px';

//Media player code

$display_mode = $learnPath->mode;
$scorm_css_header = true;
$lp_theme_css = $learnPath->get_theme();

// Setting up the CSS theme if exists.
if (!empty($lp_theme_css) && !empty($mycourselptheme) && $mycourselptheme != -1 && $mycourselptheme == 1) {
    global $lp_theme_css;
} else {
    $lp_theme_css = $my_style;
}

$progress_bar = "";
if (!api_is_invitee()) {
    $progress_bar = $learnPath->getProgressBar();
}
$navigation_bar = $learnPath->get_navigation_bar();
$navigation_bar_bottom = $learnPath->get_navigation_bar(
    "control-bottom",
    "display:none"
);
$mediaplayer = $learnPath->get_mediaplayer($autostart);

$tbl_lp_item = Database::get_course_table(TABLE_LP_ITEM);
$show_audioplayer = false;
// Getting all the information about the item.
$sql = "SELECT audio FROM " . $tbl_lp_item . "
        WHERE c_id = $course_id AND lp_id = '".$learnPath->lp_id."'";
$res_media = Database::query($sql);

if (Database::num_rows($res_media) > 0) {
    while ($row_media = Database::fetch_array($res_media)) {
        if (!empty($row_media['audio'])) {
            $show_audioplayer = true;
            break;
        }
    }
}

$is_allowed_to_edit = api_is_allowed_to_edit(false, true, true, false);

$breadcrumb = null;

if ($is_allowed_to_edit) {
    global $interbreadcrumb;
    $interbreadcrumb[] = array(
        'url' => 'lp_controller.php?action=list&isStudentView=false',
        'name' => get_lang('LearningPaths')
    );
    $interbreadcrumb[] = array(
        'url' => api_get_self(
            )."?action=add_item&type=step&lp_id={$learnPath->lp_id}&isStudentView=false",
        'name' => $learnPath->get_name(),
    );
    $interbreadcrumb[] = array(
        'url' => '#',
        'name' => get_lang('Preview')
    );
    $breadcrumb = Display::returnBreadcrumb($interbreadcrumb, null, null);
}

// Return to course home.
if ($is_allowed_to_edit) {
    $buttonHomeUrl = 'lp_controller.php?' . api_get_cidreq() . '&' . http_build_query([
        'isStudentView' => 'false',
        'action' => 'return_to_course_homepage'
    ]);
} else {
    $buttonHomeUrl = 'lp_controller.php?' . api_get_cidreq() . '&' . http_build_query([
        'action' => 'return_to_course_homepage'
    ]);
}

$buttonHomeText = get_lang('CourseHomepageLink');
// Return to lp list
if (api_get_course_setting('lp_return_link') == 1) {
    $buttonHomeUrl .= '&redirectTo=lp_list';
    $buttonHomeText = get_lang('LearningPathList');
}

$lpPreviewImagePath = api_get_path(WEB_IMG_PATH).'icons/64/unknown.png';
if ($learnPath->get_preview_image()) {
    $lpPreviewImagePath = $learnPath->get_preview_image_path();
}

if ($learnPath->current == $learnPath->get_last()) {
    $categories = Category::load(null, null, $course_code, null, null, $sessionId);

    if (!empty($categories)) {
        $gradebookEvaluations = $categories[0]->get_evaluations();
        $gradebookLinks = $categories[0]->get_links();

        if (
            count($gradebookEvaluations) === 0 &&
            count($gradebookLinks) === 1 &&
            $gradebookLinks[0]->get_type() == LINK_LEARNPATH &&
            $gradebookLinks[0]->get_ref_id() == $learnPath->lp_id
        ) {
            $gradebookMinScore = $categories[0]->get_certificate_min_score();
            $userScore = $gradebookLinks[0]->calc_score($user_id, 'best');

            if ($userScore[0] >= $gradebookMinScore) {
                Category::register_user_certificate($categories[0]->get_id(), $user_id);
            }
        }
    }
}

$template = \Chamilo\CoreBundle\Framework\Container::getTwig();
$template->addGlobal(
    'glossary_extra_tools',
    api_get_setting('glossary.show_glossary_in_extra_tools')
);

$fixLinkSetting = api_get_configuration_value('lp_fix_embed_content');
$fixLink = '';
if ($fixLinkSetting) {
    $fixLink = '{type:"script", id:"_fr10", src:"'.api_get_path(WEB_LIBRARY_PATH).'javascript/fixlinks.js"}';
}

$template->addGlobal('fix_link', $fixLink);
$template->addGlobal(
    'glossary_tool_availables',
    ['true', 'lp', 'exercise_and_lp']
);

// If the global gamification mode is enabled...
$gamificationMode = api_get_setting('platform.gamification_mode');
// ...AND this learning path is set in gamification mode, then change the display
$gamificationMode = $gamificationMode && $learnPath->seriousgame_mode;

$template->addGlobal(
    'show_glossary_in_documents',
    api_get_setting('document.show_glossary_in_documents')
);
$template->addGlobal('jquery_web_path', api_get_jquery_web_path());
$template->addGlobal('jquery_ui_js_web_path', api_get_jquery_ui_js_web_path());
$template->addGlobal(
    'jquery_ui_css_web_path',
    api_get_jquery_ui_css_web_path()
);
$template->addGlobal('is_allowed_to_edit', $is_allowed_to_edit);
$template->addGlobal('gamification_mode', $gamificationMode);
$template->addGlobal('breadcrumb', $breadcrumb);
$template->addGlobal('button_home_url', $buttonHomeUrl);
$template->addGlobal('button_home_text', $buttonHomeText);
$template->addGlobal('navigation_bar', $navigation_bar);
$template->addGlobal('progress_bar', $progress_bar);
$template->addGlobal('show_audio_player', $show_audioplayer);
$template->addGlobal('media_player', $mediaplayer);
$template->addGlobal('toc_list', $get_toc_list);
$template->addGlobal('teacher_toc_buttons', $get_teacher_buttons);
$template->addGlobal('iframe_src', $src);
$template->addGlobal('navigation_bar_bottom', $navigation_bar_bottom);

if ($gamificationMode == 1) {
    $template->addGlobal(
        'gamification_stars',
        $learnPath->getCalculateStars($sessionId)
    );
    $template->addGlobal(
        'gamification_points',
        $learnPath->getCalculateScore($sessionId)
    );
}

$template->addGlobal(
    'lp_preview_image',
    Display::img(
        $lpPreviewImagePath,
        $learnPath->name,
        array('class' => 'img-circle'),
        ICON_SIZE_BIG
    )
);

$template->addGlobal('lp_author', $learnPath->get_author());
$template->addGlobal('lp_mode', $learnPath->mode);
$template->addGlobal('lp_title_scorm', $learnPath->name);
$template->addGlobal(
    'lp_html_toc',
    $learnPath->get_html_toc($get_toc_list)
);
$template->addGlobal('lp_id', $learnPath->lp_id);
$template->addGlobal(
    'lp_current_item_id',
    $learnPath->get_current_item_id()
);

$content = $template->render('@template_style/learnpath/view.html.twig');
echo $content;

// Restore a global setting.
$_setting['show_navigation_menu'] = $save_setting;

// Hide headers
Container::$legacyTemplate = 'layout_one_col_no_content.html.twig';

$learnPath->updateCurrentLpFromSession();
