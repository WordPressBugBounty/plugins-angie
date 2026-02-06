(function(){
	var tbody=document.getElementById('angie-snippet-files-rows');
	var addBtn=document.getElementById('angie-add-file');
	var baseSettings=window.AngieCodeEditorSettings||{};

	function modeFromFilename(name){
		if(!name){return 'text/plain';}
		var ext=name.split('.').pop().toLowerCase();
		switch(ext){
			case 'php': return 'text/x-php';
			case 'js': case 'mjs': case 'cjs': return 'javascript';
			case 'ts': case 'tsx': case 'jsx': return 'javascript';
			case 'css': return 'text/css';
			case 'scss': return 'text/x-scss';
			case 'less': return 'text/x-less';
			case 'html': case 'htm': return 'text/html';
			case 'json': return 'application/json';
			case 'yml': case 'yaml': return 'text/x-yaml';
			case 'xml': return 'application/xml';
			case 'sh': case 'bash': return 'text/x-sh';
			default: return 'text/plain';
		}
	}

	function validateFilenames(){
		var inputs=tbody.querySelectorAll('input.angie-filename');
		var filenames={};
		var hasDuplicate=false;
		Array.prototype.forEach.call(inputs, function(input){
			var name=(input.value||'').trim();
			if(name===''){return;}
			if(filenames[name]){
				hasDuplicate=true;
				input.style.borderColor='#dc3232';
			}else{
				filenames[name]=true;
				input.style.borderColor='';
			}
		});
		return !hasDuplicate;
	}

	function initRow(tr){
		var textarea=tr.querySelector('textarea.angie-file-content');
		var nameInput=tr.querySelector('input.angie-filename');
		if(!textarea||!nameInput||!window.wp||!wp.codeEditor){return;}
		var settings=JSON.parse(JSON.stringify(baseSettings));
		settings.codemirror=settings.codemirror||{};
		settings.codemirror.mode=modeFromFilename(nameInput.value||'');
		settings.codemirror.lineNumbers=false;
		settings.codemirror.lineWrapping=true;
		var editor=wp.codeEditor.initialize(textarea, settings);
		if(editor && editor.codemirror){
			nameInput.addEventListener('input', function(){
				var mode=modeFromFilename(this.value||'');
				editor.codemirror.setOption('mode', mode);
				validateFilenames();
			});
		}
	}

	function bindRemove(btn){
		btn.addEventListener('click',function(){
			var tr=this.closest('tr');
			if(tr && tbody.children.length>1){
				tr.remove();
				validateFilenames();
			}
		});
	}

	Array.prototype.forEach.call(document.querySelectorAll('tr.angie-snippet-file-row'), function(tr){
		var btn=tr.querySelector('.angie-remove-file');
		if(btn){bindRemove(btn);}
		initRow(tr);
	});

	addBtn.addEventListener('click',function(){
		var index=tbody.children.length;
		var tr=document.createElement('tr');
		tr.className='angie-snippet-file-row';
		tr.innerHTML =
			'<td><input type="text" name="angie_snippet_files['+index+'][name]" class="widefat angie-filename" placeholder="'+window.angieFilesMetaBox.placeholders.name+'" /></td>'+
			'<td><textarea name="angie_snippet_files['+index+'][content]" class="widefat angie-file-content" rows="8" placeholder="'+window.angieFilesMetaBox.placeholders.content+'" ></textarea></td>'+
			'<td><button type="button" class="button link-delete angie-remove-file">'+window.angieFilesMetaBox.labels.remove+'</button></td>';
		tbody.appendChild(tr);
		var btn=tr.querySelector('.angie-remove-file');
		if(btn){bindRemove(btn);}
		initRow(tr);
	});

	var form=document.getElementById('post');
	if(form){
		form.addEventListener('submit',function(e){
			if(!validateFilenames()){
				e.preventDefault();
				alert('Duplicate filenames are not allowed. Please ensure all filenames are unique.');
				return false;
			}
		});
	}

	validateFilenames();
})();
