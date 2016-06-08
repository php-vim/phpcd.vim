let s:save_cpo = &cpo
set cpo&vim

if (!exists('g:phpcd_options'))
	let g:phpcd_options = {}
	let g:phpcd_options.completion = {}
	let g:phpcd_options.logger = {}
endif

if (!exists('g:phpcd_options.completion.match_type'))
	let g:phpcd_options.completion.match_type = 'head'
endif

let g:phpcd_need_update = 0
let g:phpcd_channel_id = -1
let g:phpid_channel_id = -1

autocmd BufLeave,VimLeave *.php if g:phpcd_need_update > 0 | call phpcd#UpdateIndex() | endif
autocmd BufWritePost *.php let g:phpcd_need_update = 1

let &cpo = s:save_cpo
unlet s:save_cpo

" vim: foldmethod=marker:noexpandtab:ts=2:sts=2:sw=2
