<?php

/**
 * Hoa
 *
 *
 * @license
 *
 * New BSD License
 *
 * Copyright © 2007-2013, Ivan Enderlin. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the Hoa nor the names of its contributors may be
 *       used to endorse or promote products derived from this software without
 *       specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS AND CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace {

from('Hoa')

/**
 * \Hoa\Eventsource\Exception
 */
-> import('Eventsource.Exception')

/**
 * \Hoa\Http\Runtime
 */
-> import('Http.Runtime')

/**
 * \Hoa\Http\Response
 */
-> import('Http.Response.~');

}

namespace Hoa\Eventsource {

/**
 * Class \Hoa\Eventsource\Server.
 *
 * A cross-protocol EventSource server.
 *
 * @author     Ivan Enderlin <ivan.enderlin@hoa-project.net>
 * @copyright  Copyright © 2007-2013 Ivan Enderlin.
 * @license    New BSD License
 */

class Server {

    /**
     * Mime type.
     *
     * @const string
     */
    const MIME_TYPE = 'text/event-stream';

    /**
     * Current event.
     *
     * @var \Hoa\Eventsource\Server string
     */
    protected $_event    = null;

    /**
     * HTTP response.
     *
     * @var \Hoa\Http\Response object
     */
    protected $_response = null;



    /**
     * Start an event source.
     *
     * @access  public
     * @param   bool  $verifyHeaders    Verify headers or not.
     * @return  void
     * @throw   \Hoa\Eventsource\Exception
     */
    public function __construct ( $verifyHeaders = true ) {

        if(true === $verifyHeaders && true === headers_sent($file, $line))
            throw new Exception(
                'Headers already sent in %s at line %d, cannot send data ' .
                'to client correctly.',
                0, array($file, $line));

        $mimes  = preg_split('#\s*,\s*#', \Hoa\Http\Runtime::getHeader('accept'));
        $gotcha = false;

        foreach($mimes as $mime)
            if(0 !== preg_match('#^' . self::MIME_TYPE . ';?#', $mime)) {

                $gotcha = true;
                break;
            }

        $this->_response = new \Hoa\Http\Response(false);

        if(false === $gotcha) {

            $this->_response->sendHeader(
                'Status',
                \Hoa\Http\Response::STATUS_NOT_ACCEPTABLE
            );

            throw new Exception(
                'Client does not accept %s.', 1, self::MIME_TYPE);
        }

        $this->_response->sendHeader('Content-Type',      self::MIME_TYPE);
        $this->_response->sendHeader('Transfer-Encoding', 'identity');
        $this->_response->sendHeader('Cache-Control',     'no-cache');
        $this->_response->newBuffer();

        return;
    }

    /**
     * Send an event.
     *
     * @access  public
     * @param   string  $data     Data.
     * @param   string  $id       ID (empty string to reset).
     * @return  void
     */
    public function send ( $data, $id = null ) {

        if(null !== $this->_event) {

            $this->_response->writeAll('event: ' . $this->_event . "\n");
            $this->_event = null;
        }

        $this->_response->writeAll(
            'data: ' .
            preg_replace("#(" . CRLF . "|\n|\r)#", "\n" . 'data: >', $data)
        );

        if(null !== $id) {

            $this->_response->writeAll("\n" . 'id');

            if(!empty($id))
                $this->_response->writeAll(': ' . $id);
        }

        $this->_response->writeAll("\n\n");
        $this->_response->flush(true);

        return;
    }

    /**
     * Set the reconnection time for the client.
     *
     * @access  public
     * @param   int    $ms    Time in milliseconds.
     * @return  void
     */
    public function setReconnectionTime ( $ms ) {

        $this->_response->writeAll('retry: ' . $ms . "\n\n");
        $this->_response->flush(true);

        return;
    }

    /**
     * Select an event where to send data.
     *
     * @access  public
     * @param   string  $event    Event.
     * @return  \Hoa\Eventsource\Server
     * @throw   \Hoa\Eventsource\Exception
     */
    public function __get ( $event ) {

        if(false === (bool) preg_match('##u', $event))
            throw new Exception(
                'Event name %s must be in UTF-8.', 2, $event);

        if(0 !== preg_match('#[:' . CRLF . ']#u', $event))
            throw new Exception(
                'Event name %s contains illegal characters.', 3, $event);

        $this->_event = $event;

        return $this;
    }

    /**
     * Get last ID.
     *
     * @access  public
     * @return  string
     */
    public function getLastId ( ) {

        return \Hoa\Http\Runtime::getHeader('Last-Event-ID') ?: '';
    }
}

}
