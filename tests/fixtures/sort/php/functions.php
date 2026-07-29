<?php

function field($type, ...$args){
	return phlo("field_$type", ...$args, type: $type);
}

function tag(string $tagName, ?string $inner = null, ...$args){
	return "<$tagName".loop(array_filter($args, fn($value) => !is_null($value)), fn($value, $key) => space.strtr($key, [us => dash]).($value === true ? void : '="'.esc($value).'"'), void).'>'.(is_null($inner) ? void : "$inner</$tagName>");
}

function main(string $main, $title = null, ...$args){
	$layout = phlo('CMS_layout'.(($variant = phlo('app')->layout) ? "_$variant" : void));
	$main = tag('main', indentView(lf.trim($main).lf));
	$args['path'] ??= phlo('req')->path;
	if (phlo('req')->async) view(...$args, title: $title, settings: ['scroll' => 'main'], outer: ['nav' => $layout->nav], main: $main, trans: phlo('app')->trans ?: 'page');
	else view($layout.lf.$main, $title, ...$args, settings: ['scroll' => 'main']);
}

function CMS(string $model, $mode, ...$args){
	return phlo('CMS_'.$mode.(($type = $model::${$mode.'View'} ?? phlo('app')->$mode) ? "_$type" : void), $model, ...$args, type: $type ?: 'default', isMain: first(!phlo('app')->mainCMS, phlo('app')->mainCMS = true));
}

