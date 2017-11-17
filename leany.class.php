<?php
/*
	Leany - A lean, no-frills PHP template compiler

	Copyright (c) 2017, Martin Wandelt

	...................................................................
	The MIT License (MIT)

	Permission is hereby granted, free of charge, to any person
	obtaining a copy of this software and associated documentation files
	(the "Software"), to deal in the Software without restriction,
	including without limitation the rights to use, copy, modify, merge,
	publish, distribute, sublicense, and/or sell copies of the Software,
	and to permit persons to whom the Software is furnished to do so,
	subject to the following conditions:

	The above copyright notice and this permission notice shall be
	included in all copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
	EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
	MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
	NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS
	BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
	ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
	CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
	SOFTWARE.
	...................................................................
*/


class Leany {
	
	public $baseDir; // Base directory for resolving relative template paths
	public $cacheDir; // Path to cache directory (must be writable)
	public $baseUrlLinks; // Base URL for the {{link:...}} placeholder
	public $baseUrlResources; // Base URL for replacing relative resource paths within CDATA blocks
	public $outputFunction; // Function for preprocessing variables before output


	public function __construct( $baseDir = '', $cacheDir = NULL )
	{
		$this->baseDir = $baseDir;
		$this->cacheDir = $cacheDir;
	}


	public function compile( $template, $ignoreCache = FALSE )
	{
		$sourceFile = $template[0] == '/' ? $template : "{$this->baseDir}/{$template}";

		if ( ! $ignoreCache && ! empty( $this->cacheDir ) )
		{
			$cacheFile = "{$this->cacheDir}/{$template}.php";
			
			if ( file_exists( $cacheFile ) 
					&& filemtime( $cacheFile ) >= filemtime( $sourceFile ) )
			{
				return file_get_contents( $cacheFile );
			}
		}

		if ( ! file_exists( $sourceFile ) )
		{
			return FALSE;
		}
		
		$code = $this->compile_string( file_get_contents( $sourceFile ) );

		if ( ! empty( $cacheFile ) )
		{
			file_put_contents( $cacheFile, $code );
		}

		return $code;
	}


	public function compile_string( $code )
	{
		$code = str_replace( array ( '<!--{{', '}}-->' ), array ( '{{', '}}' ), $code );
		$arr = explode( '{{', $code );
		$result = $this->handle_cdata( $arr[0] );

		for ( $i = 1; $i < sizeof( $arr ); $i++ )
		{
			$pos = strpos( $arr[ $i ], '}}' );
			$result .= $this->handle_tag( substr( $arr[ $i ], 0, $pos) );
			$result .= $this->handle_cdata( substr( $arr[ $i ], $pos + strlen( '}}' ) ) );
		}

		return $result;
	}


	public function clear_cache()
	{
		if ( empty( $this->cacheDir ) )
		{
			return FALSE;
		}
		
		$list = scandir( $this->cacheDir );
		
		foreach ( $list as $filename )
		{
			if ( $filename[0] != '.' )
			{
				unlink( $this->cacheDir . '/' . $filename );
			}
		}
		
		return TRUE;
	}
	

	private function handle_cdata( $code )
	{
		if ( empty( $this->baseUrlResources ) )
		{
			return $code;
		}

		$search = '|([\("\\\'])([a-zA-Z0-9%_-][a-zA-Z0-9%_\.-]+/[a-zA-Z0-9%_/\.-]+\.[a-zA-Z0-9_]+)(["\\\'\)])|';
		$replace = '\\1' . $this->baseUrlResources . '/\\2\\3';
		return preg_replace( $search, $replace, $code );
	}
	

	private function handle_tag( $code )
	{
		if ( $code[0] == '_' )
		{
			return '';
		}

		list( $code, $formatString ) = explode( '|', $code, 2 ) + array ( '', '' );
		list( $name, $arg ) = explode( ':', $code, 2 ) + array ( '', '' );
		$function = 'handle_tag_' . $name;

		if ( method_exists( $this, $function ) )
		{
			return $this->$function( $arg, $formatString );
		}
		
		$var = $this->_arg2var( $name );
		
		if ( empty( $this->outputFunction ) )
		{
			return "<?php echo {$var}; ?>";
		}
		else
		{
			return "<?php echo {$this->outputFunction}( {$var}, '{$formatString}' ); ?>";
		}
	}

	
	private function handle_tag_if( $arg )
	{
		if ( strpos( $arg, '$' ) !== FALSE )
		{
			return "<?php if ( {$arg} ){ ?>";
		}

		$var = $this->_arg2var( $arg );
		return "<?php if ( ! empty( {$var} ) ){ ?>";
	}


	private function handle_tag_endif()
	{
		return '<?php } ?>';
	}


	private function handle_tag_elseif( $arg )
	{
		if ( strpos( $arg, '$' ) !== FALSE )
		{
			return "<?php } elseif ( {$arg} ){ ?>";
		}

		$var = $this->_arg2var( $arg );
		return "<?php } elseif ( ! empty( {$var} ) ){ ?>";
	}


	private function handle_tag_else()
	{
		return '<?php } else { ?>';
	}


	private function handle_tag_link( $arg )
	{
		$arg = trim( $arg );
		return "{$this->baseUrlLinks}/{$arg}";
	}


	private function handle_tag_loop( $arg, $varname )
	{
		$var = $this->_arg2var( $arg );
		$varname = empty( $varname ) ? 'item' : $varname;
		return "<?php {$var}->data_seek(0); while ( \${$varname} = {$var}->fetch_assoc() ){ ?>";
	}


	private function handle_tag_endloop()
	{
		return '<?php } ?>';
	}


	private function handle_tag_repeat( $arg, $varname )
	{
		$var = $this->_arg2var( $arg );
		$varname = empty( $varname ) ? 'item' : $varname;
		return "<?php foreach ( {$var} as \${$varname} ){ ?>";
	}


	private function handle_tag_endrepeat()
	{
		return '<?php } ?>';
	}


	private function handle_tag_include( $arg )
	{
		return $this->compile( trim( $arg ) );
	}
	
	
	private function _arg2var( $arg )
	{
		if ( strpos( $arg, '.' ) === FALSE )
		{
			return '$' . trim( $arg );
		}

		$parts = explode( '.', trim( $arg ) );
		return '$' . array_shift( $parts ) . "['" . implode( "']['", $parts ) . "']";
	}
}


// end of file leany.class.php
