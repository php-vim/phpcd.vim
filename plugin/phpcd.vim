let s:save_cpo = &cpo
set cpo&vim

let g:phpcd_need_update = 0
let g:phpcd_channels = {}
let g:phpid_channels = {}

let s:phpcd_path = expand('<sfile>:p:h:h') . '/php/phpcd_main.php'
let s:phpid_path = expand('<sfile>:p:h:h') . '/php/phpid_main.php'

function! phpcd#DetectComposerRoot(path) " {{{
	let path = fnamemodify(a:path, ':p')
	let root = ''

	while path != "/"
		if (filereadable(path . "/vendor/autoload.php"))
			let root = path
			break
		endif
		let path = fnamemodify(path, ":h")
	endwhile
	return root
endfunction " }}}

function! phpcd#InitBufferContext()
	let save_cpo = &cpo
	set cpo&vim

	if exists('b:composer_root')
		let root = b:composer_root
	else
		let root = phpcd#DetectComposerRoot(expand('%:p:h'))
		if empty(root)
			let &cpo = save_cpo
			unlet save_cpo
			return
		endif
		let b:composer_root = root
	endif

	call phpcd#InitComposerRoot(root)

	let &cpo = save_cpo
	unlet save_cpo
endfunction

function! phpcd#InitComposerRoot(root)
	let root = fnamemodify(a:root, ':p')
	if !has_key(g:phpcd_channels, root)
		let g:phpcd_channels[root] = rpcstart('php', [s:phpcd_path, root])
	endif

	if !has_key(g:phpid_channels, root)
		let g:phpid_channels[root] = rpcstart('php', [s:phpid_path, root])
	endif
endfunction

function! phpcd#GetBufferPhpcdChannel()
	if !exists('b:composer_root')
		return 0
	endif

	return phpcd#getComposerRootPhpcdChannel(b:composer_root)
endfunction

function! phpcd#getComposerRootPhpcdChannel(root)
	let root = fnamemodify(a:root, ':p')
	if !has_key(g:phpcd_channels, root)
		return 0
	endif
	return g:phpcd_channels[root]
endfunction

function! phpcd#GetBufferPhpidChannel()
	if !exists('b:composer_root')
		return 0
	endif

	return phpcd#getComposerRootPhpidChannel(b:composer_root)
endfunction

function! phpcd#getComposerRootPhpidChannel(root)
	let root = fnamemodify(a:root, ':p')
	if !has_key(g:phpid_channels, root)
		return 0
	endif
	return g:phpid_channels[root]
endfunction

autocmd filetype php call phpcd#InitBufferContext()
autocmd BufLeave,VimLeave *.php if g:phpcd_need_update > 0 | call phpcd#UpdateIndex() | endif
autocmd BufWritePost *.php let g:phpcd_need_update = 1

let &cpo = s:save_cpo
unlet s:save_cpo

" vim: foldmethod=marker:noexpandtab:ts=2:sts=2:sw=2
