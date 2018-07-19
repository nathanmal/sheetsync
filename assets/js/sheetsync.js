jQuery(function($){

    if( $('#sheetsync_edit').length ){

      var table   = $('#ss_sheet_mapping_table');
      var columns = $.parseJSON($('#ss_header_labels').val());
      var attributes   = $.parseJSON($('#ss_attributes').val());

      var getUsedColumns = function(){
        var cols = [];
        $(table).find('tbody tr').each(function(row){

        });
      }

      var createColumnDD = function(){
        var used = getUsedColumns();
        var cols = [];
        var select = $('<select></select>').attr('name','map_cols[]');

        select.append($('<option></option>').attr('value','').text('Select Column'));

        $.each(columns, function(i,col){
          var option = $('<option></option>').attr('value',i).text(col);
          select.append(option);
        });
        return select;
      }

      var changeAttr = function(e){
        var t = e.target;
        var v = $(t).val();
        var l = $(t).find('option:selected').text();

        var an = $(t).closest('tr').find('input.regular-text');

        if( v == 'post.meta' ){
          an.removeAttr('disabled');
          an.val('');
          an.css('border','1px solid #ccc');
          an.attr('placeholder','Enter Meta Name');
        } else {
          an.css('border','none').css('box-shadow','none');
          //an.val(l);
          an.attr('disabled','disabled');
          
        }
        console.log(an);


      }

      var doSubmit = function(e){

        $(table).find('input.regular-text').each(function(input){
          $(input).removeAttr('disabled');
        });

        return true;

      }

      var createAttrDD = function(){

        var select = $('<select></select>').attr('name','map_attrs[]');

        select.append($('<option></option>').attr('value','').text('Select Attribute'));

        $.each(attributes, function(key,label){
          var option = $('<option></option>').attr('value',key).text(label);
          select.append(option);
        });

        select.on('change',changeAttr);

        return select;

      }

      var addTableRow = function(){
        var row = $('<tr></tr>');
        var colCell = $('<td></td>');
        var attrCell = $('<td></td>');
        var attrNameCell = $('<td></td>');

        var colDD = createColumnDD();
        var attrDD = createAttrDD();
        var attrName = $('<input></input>').attr('name','map_names[]').attr('type','text').attr('value','').addClass('regular-text').css('border','none').css('box-shadow','none');

        attrNameCell.append(attrName);

        colCell.append(colDD);
        attrCell.append(attrDD);

        row.append(colCell,attrCell,attrNameCell);

        $(table).find('tbody').append(row);
      }

      // Add row to mapping table
      $('#ss_add_mapping').click(function(e){
        addTableRow();
      });

      $('#sheetsync_edit').on('submit', doSubmit);
    }



});