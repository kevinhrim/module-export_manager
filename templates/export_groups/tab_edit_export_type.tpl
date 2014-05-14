  {include file='messages.tpl'}

  <form action="{$same_page}" method="post" onsubmit="return rsv.validate(this, page_ns.rules)">
    <input type="hidden" name="export_type_id" value="{$export_type.export_type_id}" />
    <input type="hidden" name="export_group_id" value="{$export_type.export_group_id}" />
    <input type="hidden" name="page" value="edit_export_type" />

	  <table cellspacing="1" cellpadding="2" border="0">
	  <tr>
	    <td width="120" class="medium_grey">{$L.phrase_export_type_name}</td>
	    <td>
	      <input type="text" name="export_type_name" value="{$export_type.export_type_name}" style="width:300px" maxlength="255" />
	    </td>
	  </tr>
	  <tr>
	    <td valign="top" class="medium_grey">{$L.word_visibility}</td>
	    <td>
	      <input type="radio" name="visibility" value="show" id="st1" {if $export_type.export_type_visibility == "show"}checked{/if} />
	        <label for="st1" class="green">{$LANG.word_show}</label>
	      <input type="radio" name="visibility" value="hide" id="st2" {if $export_type.export_type_visibility == "hide"}checked{/if} />
	        <label for="st2" class="red">{$LANG.word_hide}</label>
	      <div class="light_grey">This export type will only be displayed if the export group is visible as well!</div>
	    </td>
	  </tr>
    <tr>
	  <tr>
	    <td valign="top" class="medium_grey">{$L.word_filename}</td>
	    <td>
	      <input type="text" name="filename" value="{$export_type.filename}" style="width:200px" maxlength="50" />
	      <span class="light_grey">This is only applicable for generated files, or export groups that define headers that
	      prompt a file download. See the <a href="http://modules.formtools.org/export_manager/documentation.php?page=filename_placeholders">user documentation</a>
	      for a list of available placeholders.</span>
	    </td>
	  </tr>
		</table>
		
		<p class="bold">{$L.phrase_smarty_template}</p>
		
    <div style="border: 1px solid #666666; padding: 3px">			
      <textarea name="smarty_template" id="smarty_template" style="width:100%; height:340px">{$export_type.export_type_smarty_template}</textarea>
    </div>      

    <script type="text/javascript">
    var html_editor = new CodeMirror.fromTextArea("smarty_template", {literal}{{/literal}
    parserfile: ["parsexml.js"],
    path: "{$g_root_url}/global/codemirror/js/",
    stylesheet: "{$g_root_url}/global/codemirror/css/xmlcolors.css"
    {literal}});{/literal}
    </script>

	  <p>
	    <input type="submit" name="update_export_type" value="{$LANG.word_update}" />
	  </p>

  </form>