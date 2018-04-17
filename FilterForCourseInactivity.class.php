<?php

class FilterForCourseInactivity extends StudIPPlugin implements SystemPlugin
{
    public function __construct()
    {
        parent::__construct();

        if (stripos($_SERVER['REQUEST_URI'], "dispatch.php/admin/courses") !== false) {
            NotificationCenter::addObserver($this, "addInactivityFilterToSidebar", "SidebarWillRender");
        }

        NotificationCenter::addObserver($this, "addInactivityFilter", "AdminCourseFilterWillQuery");
    }

    public function addInactivityFilterToSidebar()
    {
        $widget = new SelectWidget(
            _("Aktivitätsfilter"),
            PluginEngine::getURL($this, array(), "change_inactivity_filter"),
            "since"
        );
        $widget->addElement(new SelectElement('', ''));
        //$widget->addElement(new SelectElement((30), _('kurz'), 30 == $GLOBALS['user']->cfg->getValue("INACTIVITY_FILTER")));
        $widget->addElement(new SelectElement((86400 * 30), _('Einen Monat inaktiv'), 86400 * 30 == $GLOBALS['user']->cfg->getValue("INACTIVITY_FILTER")));
        $widget->addElement(new SelectElement((86400 * 183), _('Halbes Jahr inaktiv'), 86400 * 183 == $GLOBALS['user']->cfg->getValue("INACTIVITY_FILTER")));
        $widget->addElement(new SelectElement((86400 * 366), _('Ein Jahr inaktiv'), 86400 * 366 == $GLOBALS['user']->cfg->getValue("INACTIVITY_FILTER")));
        $widget->addElement(new SelectElement((86400 * 365 * 2), _('2 Jahre inaktiv'), 86400 * 365 * 2 == $GLOBALS['user']->cfg->getValue("INACTIVITY_FILTER")));
        $widget->addElement(new SelectElement((86400 * 365 * 3), _('3 Jahre inaktiv'), 86400 * 365 * 3 == $GLOBALS['user']->cfg->getValue("INACTIVITY_FILTER")));
        $widget->addElement(new SelectElement((86400 * 365 * 4), _('4 Jahre inaktiv'), 86400 * 365 * 4 == $GLOBALS['user']->cfg->getValue("INACTIVITY_FILTER")));
        $widget->addElement(new SelectElement((86400 * 365 * 5), _('5 Jahre inaktiv'), 86400 * 365 * 5 == $GLOBALS['user']->cfg->getValue("INACTIVITY_FILTER")));
        $widget->addElement(new SelectElement((86400 * 365 * 6), _('6 Jahre inaktiv'), 86400 * 365 * 6 == $GLOBALS['user']->cfg->getValue("INACTIVITY_FILTER")));
        $widget->addElement(new SelectElement((86400 * 365 * 7), _('7 Jahre inaktiv'), 86400 * 365 * 7 == $GLOBALS['user']->cfg->getValue("INACTIVITY_FILTER")));
        $widget->addElement(new SelectElement((86400 * 365 * 8), _('8 Jahre inaktiv'), 86400 * 365 * 8 == $GLOBALS['user']->cfg->getValue("INACTIVITY_FILTER")));
        $widget->addElement(new SelectElement((86400 * 365 * 9), _('9 Jahre inaktiv'), 86400 * 365 * 9 == $GLOBALS['user']->cfg->getValue("INACTIVITY_FILTER")));
        $widget->addElement(new SelectElement((86400 * 365 * 10), _('10 Jahre inaktiv'), 86400 * 365 * 10 == $GLOBALS['user']->cfg->getValue("INACTIVITY_FILTER")));

        Sidebar::Get()->insertWidget($widget, "editmode", "filter_inactivity");
    }

    public function change_inactivity_filter_action()
    {
        $GLOBALS['user']->cfg->store("INACTIVITY_FILTER", Request::option("since"));
        header("Location: ".URLHelper::getURL("dispatch.php/admin/courses"));
    }

    public function addInactivityFilter($event, $filter)
    {
        if ($GLOBALS['user']->cfg->getValue("INACTIVITY_FILTER")) {

            $filter->settings['query']['where']['inactivity'] = "
                seminare.chdate <= :inactivity_threshold
            ";
            $filter->settings['query']['where']['inactivity_course'] = "
                (SELECT COALESCE(MAX(chdate), 0) AS chdate_folders 
                    FROM folders 
                    WHERE range_id = seminare.Seminar_id) <= :inactivity_threshold
            ";
            $filter->settings['query']['where']['inactivity_files'] = "
                (SELECT COALESCE(MAX(file_refs.chdate), 0) AS chdate_files 
                    FROM file_refs
                    INNER JOIN folders
                    ON file_refs.folder_id = folders.id
                    WHERE folders.range_id = seminare.Seminar_id) <= :inactivity_threshold
            ";
            $filter->settings['query']['where']['inactivity_scm'] = "
                (SELECT COALESCE(MAX(chdate), 0) AS chdate_scm 
                    FROM scm 
                    WHERE range_id = seminare.Seminar_id) <= :inactivity_threshold
            ";
            $filter->settings['query']['where']['inactivity_termine'] = "
                (SELECT COALESCE(MAX(chdate), 0) AS chdate_termine 
                    FROM termine 
                    WHERE range_id = seminare.Seminar_id) <= :inactivity_threshold
            ";
            $filter->settings['query']['where']['inactivity_news'] = "
                (SELECT COALESCE(MAX(`date`), 0) AS chdate_news 
                    FROM news_range 
                        LEFT JOIN news USING (news_id) 
                    WHERE range_id = seminare.Seminar_id) <= :inactivity_threshold
            ";
            $filter->settings['query']['where']['inactivity_lit'] = "
                (SELECT COALESCE(MAX(chdate), 0) AS chdate_lit
                    FROM lit_list 
                    WHERE range_id = seminare.Seminar_id) <= :inactivity_threshold
            ";
            if (get_config('VOTE_ENABLE')) {
                $filter->settings['query']['where']['inactivity_votes'] = "
                    (SELECT COALESCE(MAX(questionnaires.chdate), 0) AS chdate_votes
                        FROM questionnaires 
                            INNER JOIN questionnaire_assignments ON (questionnaire_assignments.questionnaire_id = questionnaires.questionnaire_id) 
                        WHERE questionnaire_assignments.range_id = seminare.Seminar_id) <= :inactivity_threshold
                ";
            }
            if (get_config('WIKI_ENABLE')) {
                $filter->settings['query']['where']['inactivity_wiki'] = "
                    (SELECT COALESCE(MAX(chdate), 0) AS chdate_wiki 
                        FROM wiki 
                        WHERE range_id = seminare.Seminar_id) <= :inactivity_threshold
                ";
            }
            foreach (PluginEngine::getPlugins('ForumModule') as $plugin) {
                $table = $plugin->getEntryTableInfo();
                $filter->settings['query']['where']['inactivity_forum_'.$plugin->getPluginId()] = "
                    (SELECT COALESCE(MAX(`". $table['chdate'] ."`), 0) AS chdate
                        FROM `". $table['table'] ."` 
                        WHERE `". $table['seminar_id'] ."` =  seminare.Seminar_id) <= :inactivity_threshold
                ";
            }
            $filter->settings['parameter']['inactivity_threshold'] = time() - $GLOBALS['user']->cfg->getValue("INACTIVITY_FILTER");

            //var_dump($filter->createQuery());die();
        }
    }
}