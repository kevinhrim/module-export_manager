<?php


namespace FormTools\Modules\ExportManager;

use FormTools\Core;
use FormTools\Hooks;
use FormTools\Module as FormToolsModule;
use FormTools\Modules;
use FormTools\Sessions;
use FormTools\Settings;
use PDOException;


class Module extends FormToolsModule
{
    protected $moduleName = "Export Manager";
    protected $moduleDesc = "Define your own ways of exporting form submission data for view / download. Excel, Printer-friendly HTML, XML and CSV are included by default.";
    protected $author = "Ben Keen";
    protected $authorEmail = "ben.keen@gmail.com";
    protected $authorLink = "http://formtools.org";
    protected $version = "3.0.0";
    protected $date = "2017-10-01";
    protected $originLanguage = "en_us";
    protected $jsFiles = array();
    protected $cssFiles = array("css/styles.css");

    protected $nav = array(
        "module_name"           => array("index.php", false),
        "word_settings"         => array("settings.php", true),
        "phrase_reset_defaults" => array("reset.php", true),
        "word_help"             => array("help.php", true)
    );

    public function install($module_id)
    {
        $db = Core::$db;
        $L = $this->getLangStrings();

        $success = true;
        $message = "";

        try {
            $db->beginTransaction();

            $this->createTables();

            General::clearTableData();
            $this->addHtmlExportGroup();
            $this->addExcelExportGroup();
            $this->addXmlExportGroup();
            $this->addCsvExportGroup();
            $this->addModuleSettings();

            $db->processTransaction();

        } catch (PDOException $e) {
            $db->rollbackTransaction();
            $success = false;
            print_r($e);
            $message = $L["notify_installation_problem_c"] . " <b>" . $e->getMessage() . "</b>";
        }

        Hooks::registerHook("template", "export_manager", "admin_submission_listings_bottom", "", "displayExportOptions");
        Hooks::registerHook("template", "export_manager", "client_submission_listings_bottom", "", "displayExportOptions");

        return array($success, $message);
    }

    public function uninstall($module_id)
    {
        $db = Core::$db;

        $db->query("DROP TABLE {PREFIX}module_export_groups");
        $db->execute();

        $db->query("DROP TABLE {PREFIX}module_export_group_clients");
        $db->execute();

        $db->query("DROP TABLE {PREFIX}module_export_types");
        $db->execute();

        return array(true, "");
    }


    /**
     * This hook function is what actually outputs the Export options at the bottom of the Submission Listing page.
     *
     * @param string $template_name
     * @param array $params
     */
    public function displayExportOptions($template_name, $params)
    {
        $account_id = Sessions::get("account.account_id");
        $root_url = Core::getRootUrl();
        $smarty = Core::$smarty;

        $form_id = $params["form_id"];
        $view_id = $params["view_id"];

        $is_admin = ($template_name == "admin_submission_listings_bottom");
        if ($is_admin) {
            $account_id = "admin";
        }

        // this does all the hard work of figuring out what groups & types should appear
        $export_groups = ExportTypes::getAssignedExportTypes($account_id, $form_id, $view_id);

        // now for the fun stuff! We loop through all export groups and log all the settings for
        // each of the fields, based on incoming POST values
        $page_vars = array();
        foreach ($export_groups as $export_group) {
            $export_group_id = $export_group["export_group_id"];
            $page_vars["export_group_{$export_group_id}_results"] = Modules::loadModuleField("export_manager",
                "export_group_{$export_group_id}_results", "export_group_{$export_group_id}_results");
            $page_vars["export_group_{$export_group_id}_export_type"] = Modules::loadModuleField("export_manager",
                "export_group_{$export_group_id}_export_type", "export_group_{$export_group_id}_export_type");
        }

        $params["LANG"]["export_manager"] = ft_get_module_lang_file_contents("export_manager");

        // now pass the information to the Smarty template to display
        $smarty->assign("export_groups", $export_groups);
        $smarty->assign("is_admin", $is_admin);
        $smarty->assign("page_vars", $page_vars);
        $smarty->assign("LANG", $params["LANG"]);
        $smarty->assign("export_icon_folder", "$root_url/modules/export_manager/images/icons");

        echo $smarty->fetch("../../modules/export_manager/templates/export_options_html.tpl");
    }


    private function addHtmlExportGroup()
    {
        $LANG = Core::$L;
        $L = $this->getLangStrings();

        $smarty_template =<<< END
<html>
<head>
    <title>{\$export_group_name}</title>
    {literal}
    <style type="text/css">
    body { margin: 0px; }
    table, td, tr, div, span {
        font-family: verdana; font-size: 8pt;
    }
    table { empty-cells: show }
    #nav_row { background-color: #efefef; padding: 10px; }
    #export_group_name { color: #336699; font-weight:bold }
    .print_table { border: 1px solid #dddddd; }
    .print_table th {
        border: 1px solid #cccccc;
        background-color: #efefef;
        text-align: left;
    }
    .print_table td { border: 1px solid #cccccc; }
    .one_item { margin-bottom: 15px; }
    .page_break { page-break-after: always; }
    </style>
    <style type="text/css" media="print">
    .no_print { display: none }
    </style>
    {/literal}
</head>
<body>
    <div id="nav_row" class="no_print">
        <span style="float:right">
            {if \$page_type != "file"}
                {* if there's more than one export type in this group, display the types in a dropdown *}
                {if \$export_types|@count > 1}
                    <select name="export_type_id" onchange="window.location='{\$same_page}?export_group_id={\$export_group_id}&export_group_{\$export_group_id}_results={\$export_group_results}&export_type_id=' + this.value">
                    {foreach from=\$export_types item=export_type}
                        <option value="{\$export_type.export_type_id}" {if \$export_type.export_type_id == \$export_type_id}selected{/if}>
                            {eval var=\$export_type.export_type_name}
                        </option>
                    {/foreach}
                    </select>
                {/if}
            {/if}
            <input type="button" onclick="window.close()" value="{\$LANG.word_close}" />
            <input type="button" onclick="window.print()" value="{\$LANG.word_print}" />
        </span>
        <span id="export_group_name">{eval var=\$export_group_name}</span>
    </div>
    <div style="padding: 15px">{\$export_type_smarty_template}</div>
</body>
</html>
END;

        list ($success, $message, $export_group_id) = ExportGroups::addExportGroup(array(
            "group_name" => $L["phrase_html_printer_friendly"],
            "icon" => "printer.png",
            "action" => "popup",
            "action_button_text" => $LANG["word_display"],
            "popup_height" => 600,
            "popup_width" => 800,
            "smarty_template" => $smarty_template
        ), $L);

        $table_smarty_template =<<< END
<h1>{\$form_name} - {\$view_name}</h1>

<table cellpadding="2" cellspacing="0" width="100%" class="print_table">
<tr>
    {foreach from=\$display_fields item=column}
    <th>{\$column.field_title}</th>
    {/foreach}
</tr>
{strip}
{foreach from=\$submissions item=submission}
    {assign var=submission_id value=\$submission.submission_id}
    <tr>
        {foreach from=\$display_fields item=field_info}
            {assign var=col_name value=\$field_info.col_name}
            {assign var=value value=\$submission.\$col_name}
            <td>
                {smart_display_field form_id=\$form_id view_id=\$view_id submission_id=\$submission_id
                    field_info=\$field_info field_types=\$field_types settings=\$settings value=\$value}
            </td>
        {/foreach}
    </tr>
{/foreach}
{/strip}
</table>
END;

        ExportTypes::addExportType(array(
            "export_type_name" => $L["phrase_table_format"],
            "export_type_visibility" => "show",
            "filename" => "submissions-{\$M}.{\$j}.html",
            "export_group_id" => $export_group_id,
            "smarty_template" => $table_smarty_template
        ), $L);

        $one_by_one_smarty_template =<<< END
<h1>{\$form_name} - {\$view_name}</h1>

{strip}
{foreach from=\$submissions item=submission}
    {assign var=submission_id value=\$submission.submission_id}
    <table cellpadding="2" cellspacing="0" width="100%" class="print_table one_item">
    {foreach from=\$display_fields item=field_info}
        {assign var=col_name value=\$field_info.col_name}
        {assign var=value value=\$submission.\$col_name}
        <tr>
            <th width="140">{\$field_info.field_title}</th>
            <td>
                {smart_display_field form_id=\$form_id view_id=\$view_id submission_id=\$submission_id field_info=\$field_info
                    field_types=\$field_types settings=\$settings value=\$value}
            </td>
        </tr>
    {/foreach}
    </table>
{/foreach}
{/strip}
END;

        ExportTypes::addExportType(array(
            "export_type_name" => $L["phrase_one_by_one"],
            "export_type_visibility" => "show",
            "filename" => "submissions-{\$M}.{\$j}.html",
            "export_group_id" => $export_group_id,
            "smarty_template" => $one_by_one_smarty_template
        ), $L);

        $one_submission_smarty_template =<<< END
<h1>{\$form_name} - {\$view_name}</h1>

{foreach from=\$submissions item=submission name=row}
    {assign var=submission_id value=\$submission.submission_id}
    <table cellpadding="2" cellspacing="0" width="100%" class="print_table one_item">
    {foreach from=\$display_fields item=field_info}
        {assign var=col_name value=\$field_info.col_name}
        {assign var=value value=\$submission.\$col_name}
        <tr>
            <th width="140">{\$field_info.field_title}</th>
            <td>
                {smart_display_field form_id=\$form_id view_id=\$view_id submission_id=\$submission_id field_info=\$field_info
                    field_types=\$field_types settings=\$settings value=\$value}
            </td>
        </tr>
    {/foreach}
    </table>
    {if !\$smarty.foreach.row.last}
        <div class="no_print"><i>- {\$LANG.phrase_new_page} -</i></div>
        <br class="page_break" />
    {/if}
{/foreach}
END;

        ExportTypes::addExportType(array(
            "export_type_name" => $L["phrase_one_submission_per_page"],
            "export_type_visibility" => "show",
            "filename" => "submissions-{\$M}.{\$j}.html",
            "export_group_id" => $export_group_id,
            "smarty_template" => $one_submission_smarty_template
        ), $L);
    }

    public function addExcelExportGroup()
    {
        $L = $this->getLangStrings();

        list ($success, $message, $export_group_id) = ExportGroups::addExportGroup(array(
            "group_name" => $L["word_excel"],
            "icon" => "xls.gif",
            "action_button_text" => $L["word_generate"],
            "headers" => "Pragma: public\nCache-Control: max-age=0\nContent-Type: application/vnd.ms-excel; charset=utf-8\nContent-Disposition: attachment; filename={\$filename}",
            "smarty_template" => "<html>\n<head>\n</head>\n<body>\n\n{\$export_type_smarty_template}\n\n</body>\n</html>"
        ), $L);

        $excel_smarty_template =<<< END
<h1>{\$form_name} - {\$view_name}</h1>

<table cellpadding="2" cellspacing="0" width="100%" class="print_table">
<tr>
    {foreach from=\$display_fields item=column}
    <th>{\$column.field_title}</th>
    {/foreach}
</tr>
{strip}
{foreach from=\$submissions item=submission}
    {assign var=submission_id value=\$submission.submission_id}
    <tr>
    {foreach from=\$display_fields item=field_info}
        {assign var=col_name value=\$field_info.col_name}
        {assign var=value value=\$submission.\$col_name}
        <td>
            {smart_display_field form_id=\$form_id view_id=\$view_id submission_id=\$submission_id
                field_info=\$field_info field_types=\$field_types settings=\$settings value=\$value escape=\"excel\"}
        </td>
    {/foreach}
</tr>
{/foreach}
{/strip}
</table>
END;

        ExportTypes::addExportType(array(
            "export_type_name" => $L["phrase_table_format"],
            "export_type_visibility" => "show",
            "filename" => "submissions-{\$M}.{\$j}.html",
            "export_group_id" => $export_group_id,
            "smarty_template" => $excel_smarty_template
        ), $L);
    }

    private function addXmlExportGroup ()
    {
        $L = $this->getLangStrings();
        $LANG = Core::$L;

        list ($success, $message, $export_group_id) = ExportGroups::addExportGroup(array(
            "group_name" => $L["word_xml"],
            "visibility" => "hide",
            "icon" => "xml.jpg",
            "action_button_text" => $L["word_generate"],
            "smarty_template" => "<?xml version=\"1.0\" encoding=\"utf - 8\" ?>\r\n\r\n{\$export_type_smarty_template}"
        ), $L);

        $xml_smarty_template =<<< END
<export>
    <export_datetime>{\$datetime}</export_datetime>
    <export_unixtime>{\$U}</export_unixtime>
    <form_info>
        <form_id>{\$form_id}</form_id>
        <form_name><![CDATA[{\$form_name}]]></form_name>
        <form_url>{\$form_url}</form_url>
    </form_info>
    <view_info>
        <view_id>{\$view_id}</view_id>
        <view_name><![CDATA[{\$view_name}]]></view_name>
    </view_info>
    <submissions>
        {foreach from=\$submissions item=submission name=row}
            <submission>
            {foreach from=\$display_fields item=field_info name=col_row}
                {assign var=col_name value=\$field_info.col_name}
                {assign var=value value=\$submission.\$col_name}
                <{\$col_name}><![CDATA[{smart_display_field form_id=\$form_id 
                    view_id=\$view_id submission_id=\$submission.submission_id
                    field_info=\$field_info field_types=\$field_types
                    settings=\$settings value=\$value}]]></{\$col_name}>
            {/foreach}
        </submission>
        {/foreach}
    </submissions>
</export>
END;

        ExportTypes::addExportType(array(
            "export_type_name" => $LANG["phrase_all_submissions"],
            "export_type_visibility" => "show",
            "filename" => "form{\$form_id}_{\$datetime}.xml",
            "export_group_id" => $export_group_id,
            "smarty_template" => $xml_smarty_template
        ), $L);
    }

    private function addCsvExportGroup()
    {
        $LANG = Core::$L;
        $L = $this->getLangStrings();

        list ($success, $message, $export_group_id) = ExportGroups::addExportGroup(array(
            "group_name" => $L["word_csv"],
            "visibility" => "hide",
            "icon" => "csv.gif",
            "action_button_text" => $L["word_generate"],
            "headers" => "Content-type: application/xml; charset=\"octet - stream\"\r\nContent-Disposition: attachment; filename={\$filename}",
            "smarty_template" => "<?xml version=\"1.0\" encoding=\"utf - 8\" ?>\r\n\r\n{\$export_type_smarty_template}"
        ), $L);

        $csv_smarty_template =<<< END
{strip}\r\n  {foreach from=\$display_fields item=column name=row}\r\n    {* workaround for an absurd Microsoft Excel problem, in which the first\r\n       two characters of a file cannot be ID; see:\r\n       http://support.microsoft.com /kb/323626 *}\r\n    {if \$smarty.foreach.row.first && \$column.field_title == \"ID\"}\r\n      .ID\r\n    {else}\r\n      {\$column.field_title|escape:''csv''}\r\n    {/if}\r\n    {if !\$smarty.foreach.row.last},{/if}\r\n  {/foreach}\r\n{/strip}\r\n{foreach from=\$submissions item=submission name=row}{strip}\r\n  {foreach from=\$display_fields item=field_info name=col_row}\r\n    {assign var=col_name value=\$field_info.col_name}\r\n    {assign var=value value=\$submission.\$col_name}\r\n    {smart_display_field form_id=\$form_id view_id=\$view_id\r\n      submission_id=\$submission.submission_id field_info=\$field_info\r\n      field_types=\$field_types settings=\$settings value=\$value\r\n      escape=\"csv\"}\r\n    {* if this wasn''t the last row, output a comma *}\r\n    {if !\$smarty.foreach.col_row.last},{/if}\r\n  {/foreach}\r\n{/strip}\r\n{if !\$smarty.foreach.row.last}\r\n{/if}\r\n{/foreach}
END;

        ExportTypes::addExportType(array(
            "export_type_name" => $LANG["phrase_all_submissions"],
            "export_type_visibility" => "show",
            "filename" => "form{\$form_id}_{\$datetime}.csv",
            "export_group_id" => $export_group_id,
            "smarty_template" => $csv_smarty_template
        ), $L);
    }


    private function addModuleSettings()
    {
        $root_dir = Core::getRootDir();

        $upload_dir = str_replace("\\", "\\\\", $root_dir);
        $separator = "/";
        if (strtoupper(substr(PHP_OS, 0, 3) == 'WIN')) {
            $separator = "\\\\";
        }

        $root_url = Core::getRootUrl();
        $upload_dir .= "{$separator}upload";

        Settings::set(array(
            "file_upload_dir" => $upload_dir,
            "file_upload_url" => "$root_url/upload"
        ), "export_manager");
    }

    private function createTables()
    {
        $db = Core::$db;
        $LANG = Core::$L;

        $queries = array();
        $word_display = addcslashes($LANG["word_display"], "''");
        $queries[] = "
            CREATE TABLE {PREFIX}module_export_groups (
              export_group_id smallint(5) unsigned NOT NULL auto_increment,
              group_name varchar(255) NOT NULL,
              access_type enum('admin','public','private') NOT NULL default 'public',
              form_view_mapping enum('all','except','only') NOT NULL default 'all',
              forms_and_views mediumtext NULL,
              visibility enum('show','hide') NOT NULL default 'show',
              icon varchar(100) NOT NULL,
              action enum('file','popup','new_window') NOT NULL default 'popup',
              action_button_text varchar(255) NOT NULL default '$word_display',
              popup_height varchar(5) default NULL,
              popup_width varchar(5) default NULL,
              headers text,
              smarty_template mediumtext NOT NULL,
              list_order tinyint(4) NOT NULL,
              PRIMARY KEY  (export_group_id)
            ) DEFAULT CHARSET=utf8
        ";

        $queries[] = "
            CREATE TABLE {PREFIX}module_export_group_clients (
            export_group_id mediumint(8) unsigned NOT NULL,
            account_id mediumint(8) unsigned NOT NULL,
            PRIMARY KEY  (export_group_id, account_id)
            ) DEFAULT CHARSET=utf8
        ";

        $queries[] = "
            CREATE TABLE {PREFIX}module_export_types (
              export_type_id mediumint(8) unsigned NOT NULL auto_increment,
              export_type_name varchar(255) NOT NULL,
              export_type_visibility enum('show','hide') NOT NULL default 'show',
              filename varchar(255) NOT NULL,
              export_group_id smallint(6) default NULL,
              smarty_template text NOT NULL,
              list_order tinyint(3) unsigned NOT NULL,
              PRIMARY KEY (export_type_id)
            ) DEFAULT CHARSET=utf8
        ";

        foreach ($queries as $query) {
            $db->query($query);
            $db->execute();
        }
    }
}