let s:save_cpo = &cpo
set cpo&vim

let g:phpcd_root = '/'

if !exists('g:phpcd_php_cli_executable')
	let g:phpcd_php_cli_executable = 'php'
endif

function! GetComposerRoot() " {{{
	let root = expand("%:p:h")

	if g:phpcd_root != '/' && stridx(root, g:phpcd_root) == 0
		return g:phpcd_root
	endif

	while root != "/"
		if (filereadable(root . "/vendor/autoload.php"))
			break
		endif
		let root = fnamemodify(root, ":h")
	endwhile
	let g:phpcd_root = root
	return root
endfunction " }}}

let s:root = GetComposerRoot()

if s:root == '/'
	let &cpo = s:save_cpo
	unlet s:save_cpo
	finish
endif

silent! nnoremap <silent> <unique> <buffer> <C-]>
			\ :<C-u>call phpcd#JumpToDefinition('normal')<CR>
silent! nnoremap <silent> <unique> <buffer> <C-W><C-]>
			\ :<C-u>call phpcd#JumpToDefinition('split')<CR>
silent! nnoremap <silent> <unique> <buffer> <C-W><C-\>
			\ :<C-u>call phpcd#JumpToDefinition('vsplit')<CR>

let s:phpcd_path = expand('<sfile>:p:h:h') . '/php/main.php'
if g:phpcd_channel_id != -1
	call rpcstop(g:phpcd_channel_id)
endif

" Pass the server configuration only if json_encode function exists
if exists('*json_encode')
	let s:encoded_options = json_encode(g:phpcd_options)
else
	let s:encoded_options = ''
endif

let g:phpcd_channel_id = rpcstart(g:phpcd_php_cli_executable, [s:phpcd_path, s:root, 'PHPCD', s:encoded_options])

if g:phpid_channel_id != -1
	call rpcstop(g:phpid_channel_id)
endif

let g:phpid_channel_id = rpcstart(g:phpcd_php_cli_executable, [s:phpcd_path, s:root, 'PHPID', s:encoded_options])

let &cpo = s:save_cpo
unlet s:save_cpo

" vim: foldmethod=marker:noexpandtab:ts=2:sts=2:sw=2
